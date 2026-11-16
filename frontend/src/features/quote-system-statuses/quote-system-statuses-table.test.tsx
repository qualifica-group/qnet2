import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { quoteSystemStatuses as quoteSystemStatusesEn } from '@/i18n/locales/en-quote-system-statuses'
import { QuoteSystemStatusesTable } from '@/features/quote-system-statuses/quote-system-statuses-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The generic `<TableView>` (AG Grid + SSRM) and the app chrome
 * (`PageHeader`) are framework pieces outside this scope:
 * they are stubbed so the suite stays focused on what THIS adapter is
 * responsible for — wiring `<Can>` around the reorder toggle/create button,
 * mounting `<TableView domain="quote-system-statuses">`, and the delete flow
 * (surfacing the backend's exact 409 message).
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

const deleteQuoteSystemStatusMock = vi.fn()
vi.mock('@/features/quote-system-statuses/api', () => ({
  deleteQuoteSystemStatus: (...args: unknown[]) => deleteQuoteSystemStatusMock(...args),
  fetchQuoteSystemStatus: vi.fn(),
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

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Won' }

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
        <QuoteSystemStatusesTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { quoteSystemStatuses: quoteSystemStatusesEn }, true, true)
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  deleteQuoteSystemStatusMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  deleteQuoteSystemStatusMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('QuoteSystemStatusesTable', () => {
  it('mounts <TableView domain="quote-system-statuses"> with the reorder toggle', () => {
    renderTable()

    expect(screen.getByRole('region', { name: 'table-quote-system-statuses' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reorder' })).toBeInTheDocument()
  })

  it('hides the reorder toggle without quote-system-statuses.update', () => {
    canMock.mockReturnValue(false)

    renderTable()

    expect(screen.queryByRole('button', { name: 'Reorder' })).not.toBeInTheDocument()
  })

  it('shows the backend message, listing the linked working statuses, on a 409', async () => {
    const message = 'It is used by the working statuses: Pending (Consulenza), Waiting (Formazione).'
    deleteQuoteSystemStatusMock.mockRejectedValue(
      new AxiosError('Conflict', '409', undefined, undefined, { status: 409, data: { success: false, message } } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteQuoteSystemStatusMock).toHaveBeenCalledWith(1))
    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith(message))
  })

  it('confirms a successful delete', async () => {
    deleteQuoteSystemStatusMock.mockResolvedValue(undefined)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith('System status deleted successfully.'))
  })
})
