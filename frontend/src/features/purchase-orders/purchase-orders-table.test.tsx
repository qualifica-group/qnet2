import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import PurchaseOrdersPage from '@/pages/purchase-orders-page'
import { PurchaseOrdersTable } from '@/features/purchase-orders/purchase-orders-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * `<TableView>` and the app chrome are stubbed so the suite stays on what the
 * adapter owns: `<Can>` gating, the status tabs writing into the filter model,
 * navigation to the dedicated page and the delete outcomes.
 */
const canMock = vi.fn<(permission: string) => boolean>()
const setFilterModelMock = vi.fn()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const deletePurchaseOrderMock = vi.fn()
const downloadPurchaseOrderPdfMock = vi.fn()
vi.mock('@/features/purchase-orders/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-orders/api')>()),
  deletePurchaseOrder: (...args: unknown[]) => deletePurchaseOrderMock(...args),
  downloadPurchaseOrderPdf: (...args: unknown[]) => downloadPurchaseOrderPdfMock(...args),
}))

vi.mock('@/features/purchase-order-statuses/for-select-api', async () => {
  const { PURCHASE_ORDER_STATUS_CATALOG_FIXTURE } = await import('@/features/purchase-order-statuses/purchase-order-status-fixtures')
  return { fetchPurchaseOrderStatusCatalog: async () => PURCHASE_ORDER_STATUS_CATALOG_FIXTURE }
})

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccessMock(...args),
    error: (...args: unknown[]) => toastErrorMock(...args),
  },
}))

const ACTIONS: Record<string, TableActionDefinition> = {
  delete: { key: 'delete', label: 'actions.delete', icon: 'trash', type: 'danger', confirm: true },
  view: { key: 'view', label: 'actions.view', icon: 'eye', type: 'link', confirm: false },
  pdf: { key: 'pdf', label: 'actions.pdf', icon: 'file', type: 'link', confirm: false },
}
const ROW: TableRow = { id: 7, actions: ['view', 'delete'], code: '3/2026' }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void; setFilterModel: (model: unknown) => void },
    { domain: string; onAction?: (action: TableActionDefinition, row: TableRow) => void }
  >(function TableViewStub({ domain, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: () => {}, setFilterModel: setFilterModelMock }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button type="button" onClick={() => onAction?.(ACTIONS.delete, ROW)}>
          delete row
        </button>
        <button type="button" onClick={() => onAction?.(ACTIONS.view, ROW)}>
          open row
        </button>
        <button type="button" onClick={() => onAction?.(ACTIONS.pdf, ROW)}>
          print row
        </button>
      </div>
    )
  }),
}))

function renderWith(node: ReactNode) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <Routes>
          <Route path="/" element={node} />
          <Route path="/purchase-orders/:id" element={<p>order page</p>} />
          <Route path="/purchase-orders/new" element={<p>new order page</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function axiosError(status: number, message = 'server message') {
  return new AxiosError('error', String(status), undefined, undefined, {
    status,
    data: { success: false, message },
  } as never)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  setFilterModelMock.mockReset()
  deletePurchaseOrderMock.mockReset()
  downloadPurchaseOrderPdfMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('PurchaseOrdersPage - permission gating', () => {
  it('shows the forbidden fallback without view', () => {
    canMock.mockReturnValue(false)

    renderWith(<PurchaseOrdersPage />)

    expect(screen.getByText('You do not have permission to view purchase orders.')).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-purchase-orders' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="purchase-orders"> with view', () => {
    canMock.mockImplementation((permission) => permission === 'purchase-orders.view')

    renderWith(<PurchaseOrdersPage />)

    expect(screen.getByRole('region', { name: 'table-purchase-orders' })).toBeInTheDocument()
  })
})

describe('PurchaseOrdersTable', () => {
  it('offers "New ODA" only with create and opens the new order page', () => {
    canMock.mockImplementation((permission) => permission === 'purchase-orders.create')
    const { unmount } = renderWith(<PurchaseOrdersTable />)

    fireEvent.click(screen.getByRole('button', { name: 'New ODA' }))
    expect(screen.getByText('new order page')).toBeInTheDocument()
    unmount()

    canMock.mockReturnValue(false)
    renderWith(<PurchaseOrdersTable />)
    expect(screen.queryByRole('button', { name: 'New ODA' })).not.toBeInTheDocument()
  })

  it('writes the chosen status tab into the grid filter model, and clears it on "All"', async () => {
    renderWith(<PurchaseOrdersTable />)

    // The tab names come from the status catalog, which loads asynchronously.
    fireEvent.click(await screen.findByRole('tab', { name: 'Approved' }))
    expect(setFilterModelMock).toHaveBeenLastCalledWith({ status: { filterType: 'set', values: ['approved'] } })

    fireEvent.click(screen.getByRole('tab', { name: 'All' }))
    expect(setFilterModelMock).toHaveBeenLastCalledWith({ status: null })
  })

  it('opens the dedicated page of the row', () => {
    renderWith(<PurchaseOrdersTable />)

    fireEvent.click(screen.getByRole('button', { name: 'open row' }))

    expect(screen.getByText('order page')).toBeInTheDocument()
  })

  it('confirms the deletion with a toast on success', async () => {
    deletePurchaseOrderMock.mockResolvedValue(undefined)

    renderWith(<PurchaseOrdersTable />)
    fireEvent.click(screen.getByRole('button', { name: 'delete row' }))

    await waitFor(() => expect(deletePurchaseOrderMock).toHaveBeenCalledWith(7))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith('Purchase order deleted.'))
  })

  it('shows the backend message on a 409 and a forbidden text on a 403', async () => {
    deletePurchaseOrderMock.mockRejectedValueOnce(axiosError(409, 'Order is approved.'))
    renderWith(<PurchaseOrdersTable />)

    fireEvent.click(screen.getByRole('button', { name: 'delete row' }))
    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith('Order is approved.'))

    deletePurchaseOrderMock.mockRejectedValueOnce(axiosError(403))
    fireEvent.click(screen.getByRole('button', { name: 'delete row' }))
    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith('You cannot delete this purchase order.'))
  })

  it('downloads the PDF of the row with the `pdf` action', async () => {
    downloadPurchaseOrderPdfMock.mockResolvedValue(undefined)

    renderWith(<PurchaseOrdersTable />)
    fireEvent.click(screen.getByRole('button', { name: 'print row' }))

    await waitFor(() => expect(downloadPurchaseOrderPdfMock).toHaveBeenCalledWith(7))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith('PDF generated.'))
  })

  it('shows the server message when no layout is active (422)', async () => {
    downloadPurchaseOrderPdfMock.mockRejectedValue(axiosError(422, 'No active layout for purchase orders.'))

    renderWith(<PurchaseOrdersTable />)
    fireEvent.click(screen.getByRole('button', { name: 'print row' }))

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith('No active layout for purchase orders.'))
  })
})
