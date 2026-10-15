import { forwardRef, useImperativeHandle } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { PurchaseInvoicesTable } from '@/features/purchase-invoices/purchase-invoices-table'
import { makePurchaseInvoiceView } from '@/features/purchase-invoices/purchase-invoice-view-fixtures'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

const refreshMock = vi.hoisted(() => vi.fn())
const fetchMock = vi.hoisted(() => vi.fn<(id: number) => Promise<PurchaseInvoice>>())
const deleteMock = vi.hoisted(() => vi.fn<(id: number) => Promise<void>>())
const canMock = vi.hoisted(() => vi.fn<(permission: string) => boolean>())

vi.mock('@/features/purchase-invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoices/api')>()),
  fetchPurchaseInvoice: (id: number) => fetchMock(id),
  deletePurchaseInvoice: (id: number) => deleteMock(id),
}))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: React.ReactNode }) => <div>{actions}</div>,
}))
vi.mock('@/features/attachments/documents-section', () => ({ DocumentsSection: () => <div /> }))
vi.mock('@/features/activity-log/resource-activity-dialog', () => ({
  ResourceActivityDialog: ({ row }: { row: TableRow | null }) =>
    row ? <div role="dialog" aria-label="Activity">{`activity ${row.id}`}</div> : null,
}))
vi.mock('@/features/purchase-invoices/purchase-invoice-editor-dialog', () => ({
  // Spec 0263 (REQUIREMENT CHANGED): the editor only creates, it has no mode any more.
  PurchaseInvoiceEditorDialog: () => (
    <div role="dialog" aria-label="Invoice editor">
      new
    </div>
  ),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function action(key: string): TableActionDefinition {
  return { key, label: `actions.${key}`, icon: 'eye', type: 'action', confirm: false }
}

const ROW: TableRow = { id: 31, document_number: 'FT-9', actions: [] }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    { domain: string; onAction?: (action: TableActionDefinition, row: TableRow) => void }
  >(function TableViewStub({ domain, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock, setFilterModel: () => {}, clearSelection: () => {} }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        {['view', 'delete', 'activity'].map((key) => (
          <button key={key} type="button" onClick={() => onAction?.(action(key), ROW)}>
            {key}
          </button>
        ))}
      </div>
    )
  }),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ConfirmDialogProvider>
          <PurchaseInvoicesTable />
        </ConfirmDialogProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  fetchMock.mockResolvedValue(makePurchaseInvoiceView({ document_number: 'FT-9' }))
  deleteMock.mockResolvedValue(undefined)
  canMock.mockReturnValue(true)
})

describe('PurchaseInvoicesTable (spec 0250 AC-013)', () => {
  it('mounts the purchase-invoices domain', () => {
    renderTable()

    expect(screen.getByRole('region', { name: 'table-purchase-invoices' })).toBeInTheDocument()
  })

  it('"view" opens the document sheet with its detail', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'view' }))

    expect(await screen.findByRole('heading', { name: 'Invoice FT-9' })).toBeInTheDocument()
    expect(fetchMock).toHaveBeenCalledWith(31)
  })

  it('"delete" asks for confirmation, deletes and refreshes the grid', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'delete' }))
    expect(await screen.findByText(/Document FT-9 will be deleted/)).toBeInTheDocument()
    expect(deleteMock).not.toHaveBeenCalled()
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))

    await waitFor(() => expect(deleteMock).toHaveBeenCalledWith(31))
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
  })

  it('"activity" opens the activity log of the row', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'activity' }))

    expect(screen.getByRole('dialog', { name: 'Activity' })).toHaveTextContent('activity 31')
  })

  it('offers "New passive invoice" only with purchase-invoices.create and opens the editor in create mode', () => {
    canMock.mockImplementation((permission) => permission !== 'purchase-invoices.create')
    const { unmount } = render(
      <QueryClientProvider client={new QueryClient()}>
        <MemoryRouter>
          <PurchaseInvoicesTable />
        </MemoryRouter>
      </QueryClientProvider>,
    )
    expect(screen.queryByRole('button', { name: 'New passive invoice' })).not.toBeInTheDocument()
    unmount()

    canMock.mockReturnValue(true)
    renderTable()
    fireEvent.click(screen.getByRole('button', { name: 'New passive invoice' }))

    expect(screen.getByRole('dialog', { name: 'Invoice editor' })).toHaveTextContent('new')
  })
})
