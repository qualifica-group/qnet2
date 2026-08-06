import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import QuoteWorkflowsPage from '@/pages/quote-workflows-page'
import { QuoteWorkflowsTable } from '@/features/quote-workflows/quote-workflows-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The generic `<TableView>` (AG Grid + SSRM) and the app chrome (`PageHeader`)
 * are framework pieces outside this microtask's ownership: they are stubbed
 * so the suite stays focused on what THIS adapter is responsible for (spec
 * 0047 AC-023) — wiring `<Can>` around the table, mounting
 * `<TableView domain="quote-workflows">`, and the delete flow.
 */
const canMock = vi.fn<(permission: string) => boolean>()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

// Default modal behaviour; force the resolved open mode (spec 0042).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const deleteQuoteWorkflowMock = vi.fn()
vi.mock('@/features/quote-workflows/api', () => ({
  deleteQuoteWorkflow: (...args: unknown[]) => deleteQuoteWorkflowMock(...args),
  fetchQuoteWorkflow: vi.fn(),
  fetchDefaultStatuses: vi.fn().mockResolvedValue([]),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccessMock(...args),
    error: (...args: unknown[]) => toastErrorMock(...args),
  },
}))

const DELETE_ACTION: TableActionDefinition = {
  key: 'delete',
  label: 'actions.delete',
  icon: 'trash',
  type: 'danger',
  confirm: true,
}

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'EMEA workflow' }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    { domain: string; onAction?: (action: TableActionDefinition, row: TableRow) => void }
  >(function TableViewStub({ domain, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: () => {} }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button type="button" onClick={() => onAction?.(DELETE_ACTION, ROW)}>
          delete row
        </button>
      </div>
    )
  }),
}))

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <QuoteWorkflowsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <QuoteWorkflowsTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  deleteQuoteWorkflowMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('QuoteWorkflowsPage — permission gating (AC-023)', () => {
  it('shows the forbidden fallback and does not mount the table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(
      screen.getByText("You don't have permission to view quote workflows."),
    ).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-quote-workflows' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="quote-workflows"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'quote-workflows.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-quote-workflows' })).toBeInTheDocument()
    expect(
      screen.queryByText("You don't have permission to view quote workflows."),
    ).not.toBeInTheDocument()
  })
})

describe('QuoteWorkflowsTable — default statuses toggle', () => {
  it('shows the default-statuses button with quote-workflows.update', () => {
    canMock.mockImplementation((permission) => permission === 'quote-workflows.update')

    renderTable()

    expect(screen.getByRole('button', { name: 'Default statuses' })).toBeInTheDocument()
  })

  it('hides the default-statuses button without quote-workflows.update', () => {
    canMock.mockReturnValue(false)

    renderTable()

    expect(screen.queryByRole('button', { name: 'Default statuses' })).not.toBeInTheDocument()
  })
})

describe('QuoteWorkflowsTable — delete (AC-018)', () => {
  it('shows a forbidden toast on a 403', async () => {
    deleteQuoteWorkflowMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteQuoteWorkflowMock).toHaveBeenCalledWith(1))
    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('You cannot delete this quote workflow.'),
    )
  })

  it('shows the success toast and refreshes on a successful delete', async () => {
    deleteQuoteWorkflowMock.mockResolvedValue(undefined)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastSuccessMock).toHaveBeenCalledWith('Quote workflow deleted successfully.'),
    )
  })
})
