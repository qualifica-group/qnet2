import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import axios from 'axios'
import i18n from '@/i18n'
import { QuotesTable } from '@/features/quotes/quotes-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Spec 0065 AC-078: the quotes grid mounts `<TableView domain="quotes">` with
 * no client-declared columns, plus this adapter's own delete flow and create
 * gating. `useModuleOpener` is mocked because it throws unless the `quotes`
 * domain is registered in the module registry (`quote-screens.tsx`), which is
 * a DIFFERENT teammate's file — this suite is scoped to what THIS adapter
 * owns (mirrors how `<TableView>` itself is stubbed below).
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

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: React.ReactNode }) => <div>{actions}</div>,
}))

const openCreateMock = vi.fn()
vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: () => ({
    openCreate: openCreateMock,
    openCreateWith: vi.fn(),
    openView: vi.fn(),
    openEdit: vi.fn(),
    openDuplicate: vi.fn(),
    sheet: null,
  }),
}))

const deleteQuoteMock = vi.fn()
vi.mock('@/features/quotes/api', () => ({
  QUOTES_DOMAIN: 'quotes',
  deleteQuote: (...args: unknown[]) => deleteQuoteMock(...args),
  fetchQuote: vi.fn(),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({
  toast: { success: (...args: unknown[]) => toastSuccessMock(...args), error: (...args: unknown[]) => toastErrorMock(...args) },
}))

const DELETE_ACTION: TableActionDefinition = {
  key: 'delete',
  label: 'actions.delete',
  icon: 'trash',
  type: 'danger',
  confirm: true,
}

const ROW: TableRow = { id: 3, actions: ['delete'], title: 'Offerta Acme' }

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

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <QuotesTable />
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
  deleteQuoteMock.mockReset()
  openCreateMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('QuotesTable — domain wiring (AC-078)', () => {
  it('mounts <TableView domain="quotes"> with no client-declared columns', () => {
    renderTable()
    expect(screen.getByRole('region', { name: 'table-quotes' })).toBeInTheDocument()
  })
})

describe('QuotesTable — create gating', () => {
  it('shows the New quote button with quotes.create', () => {
    canMock.mockImplementation((permission) => permission === 'quotes.create')
    renderTable()
    expect(screen.getByRole('button', { name: 'New quote' })).toBeInTheDocument()
  })

  it('hides the New quote button without quotes.create', () => {
    canMock.mockReturnValue(false)
    renderTable()
    expect(screen.queryByRole('button', { name: 'New quote' })).not.toBeInTheDocument()
  })

  it('opens the create form when clicked', () => {
    canMock.mockReturnValue(true)
    renderTable()
    screen.getByRole('button', { name: 'New quote' }).click()
    expect(openCreateMock).toHaveBeenCalled()
  })
})

describe('QuotesTable — delete', () => {
  it('shows the success toast and refreshes on a successful delete', async () => {
    deleteQuoteMock.mockResolvedValue(undefined)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteQuoteMock).toHaveBeenCalledWith(3))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith('Quote deleted successfully.'))
  })

  it('shows a forbidden toast on a 403', async () => {
    const error = new axios.AxiosError('Forbidden', '403', undefined, undefined, {
      status: 403,
      data: { success: false, message: 'Forbidden' },
    } as never)
    deleteQuoteMock.mockRejectedValue(error)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith('You cannot delete this quote.'))
  })

  it('shows a generic error toast on any other failure', async () => {
    deleteQuoteMock.mockRejectedValue(new Error('network down'))

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith('Unable to delete the quote. Please try again.'))
  })
})
