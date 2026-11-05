import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import i18n from '@/i18n'
import { PurchaseInvoiceInstallmentsTable } from '@/features/purchase-invoice-installments/purchase-invoice-installments-table'
import { axiosError, purchaseDetail } from '@/features/purchase-invoice-installments/purchase-installment-fixtures'
import type { PurchaseInstallmentDetail } from '@/features/purchase-invoice-installments/types'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

const refreshMock = vi.fn()
const getMock = vi.fn<(id: number) => Promise<PurchaseInstallmentDetail>>()
const clearMock = vi.fn<(id: number) => Promise<PurchaseInstallmentDetail>>()
const canMock = vi.fn<(permission: string) => boolean>()
const toastError = vi.fn()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/components/page-header', () => ({ PageHeader: () => <div /> }))
vi.mock('@/features/purchase-invoice-installments/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoice-installments/api')>()),
  getPurchaseInstallment: (id: number) => getMock(id),
  clearPurchasePayment: (id: number) => clearMock(id),
}))
vi.mock('@/features/purchase-invoice-installments/purchase-financial-account-select', () => ({
  PurchaseFinancialAccountSelect: () => <div />,
}))
vi.mock('@/features/compensations/compensation-dialog', () => ({
  CompensationDialog: ({ target }: { target: unknown }) =>
    target === null ? null : <div role="dialog" aria-label="compensation dialog">{JSON.stringify(target)}</div>,
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: (message: string) => toastError(message) } }))

function action(key: string): TableActionDefinition {
  return { key, label: `actions.${key}`, icon: 'eye', type: 'action', confirm: false }
}

const ROW: TableRow = { id: 7, sequence: 2, purchase_invoice_id: 31, actions: [] }
const rowState = { reconciled: false, compensated: false }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    {
      domain: string
      forcedFilterModel?: Record<string, unknown>
      onAction?: (action: TableActionDefinition, row: TableRow) => void
      resolveActionState?: (action: TableActionDefinition, row: TableRow) => { disabled?: boolean; label?: string } | undefined
      renderFooter?: (aggregates: Record<string, number> | undefined) => ReactNode
      getBulkActions?: unknown
      onRowGroupColumnsChange?: (columnIds: string[]) => void
    }
  >(function TableViewStub({ domain, forcedFilterModel, onAction, resolveActionState, renderFooter, getBulkActions, onRowGroupColumnsChange }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock, setFilterModel: () => {}, clearSelection: () => {} }))
    return (
      <div
        role="region"
        aria-label={`table-${domain}`}
        data-forced={JSON.stringify(forcedFilterModel)}
        data-bulk={String(getBulkActions !== undefined)}
      >
        <button type="button" onClick={() => onRowGroupColumnsChange?.(['supplier'])}>
          group-supplier
        </button>
        {['record_payment', 'clear_payment', 'open_document', 'compensate'].map((key) => {
          const row = { ...ROW, reconciled: rowState.reconciled ? 'yes' : 'no', compensated: rowState.compensated ? 'yes' : 'no' }
          const state = resolveActionState?.(action(key), row)
          return (
            <button key={key} type="button" disabled={state?.disabled === true} onClick={() => onAction?.(action(key), row)}>
              {state?.label ?? key}
            </button>
          )
        })}
        {renderFooter?.({ amount: 1200, paid_amount: 200, residual_amount: 1000 })}
      </div>
    )
  }),
}))

function renderTable(entry = '/purchase-invoice-installments') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[entry]}>
        <Routes>
          <Route path="/purchase-invoice-installments" element={<PurchaseInvoiceInstallmentsTable />} />
          <Route path="/purchase-invoices/:id" element={<p>document page</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  refreshMock.mockReset()
  toastError.mockReset()
  getMock.mockReset().mockResolvedValue(purchaseDetail())
  clearMock.mockReset().mockResolvedValue(purchaseDetail())
  canMock.mockReset().mockReturnValue(true)
  rowState.reconciled = false
  rowState.compensated = false
})

describe('PurchaseInvoiceInstallmentsTable (spec 0211 AC-014, AC-016)', () => {
  it('mounts the payable domain forced to the unpaid filter and renders the footer totals', () => {
    renderTable()

    const region = screen.getByRole('region', { name: 'table-purchase-invoice-installments' })
    expect(JSON.parse(region.getAttribute('data-forced') ?? '{}')).toEqual({
      status: { filterType: 'set', values: ['unpaid'] },
    })
    expect(screen.getByRole('tab', { name: 'To pay', selected: true })).toBeInTheDocument()
    expect(screen.getByRole('group', { name: 'Totals of the current filter' })).toHaveTextContent('Paid')
  })

  it('"open document" goes to the passive invoice the installment belongs to (spec 0250 AC-013)', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'open_document' }))

    expect(screen.getByText('document page')).toBeInTheDocument()
  })

  it('"record payment" reads the installment fresh and opens the payment dialog', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'record_payment' }))

    expect(await screen.findByRole('dialog', { name: 'Record payment' })).toBeInTheDocument()
    expect(getMock).toHaveBeenCalledWith(7)
  })

  it('tells the user when the installment cannot be loaded for payment', async () => {
    getMock.mockRejectedValue(axiosError(500, {}))
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'record_payment' }))

    await waitFor(() => expect(toastError).toHaveBeenCalledWith('Unable to load the installment. Please try again.'))
    expect(screen.queryByRole('dialog', { name: 'Record payment' })).not.toBeInTheDocument()
  })

  it('"clear payment" asks for confirmation, clears and refreshes the grid', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'clear_payment' }))
    expect(clearMock).not.toHaveBeenCalled()
    fireEvent.click(await screen.findByRole('button', { name: 'Clear payment' }))

    await waitFor(() => expect(clearMock).toHaveBeenCalledWith(7))
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
  })

  it('shows the dedicated message when clearing hits the 409 conflict', async () => {
    clearMock.mockRejectedValue(axiosError(409, { message: 'Clear the later payments first.' }))
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'clear_payment' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Clear payment' }))

    await waitFor(() => expect(toastError).toHaveBeenCalledWith('Clear the later payments first.'))
    expect(refreshMock).not.toHaveBeenCalled()
  })
})

describe('PurchaseInvoiceInstallmentsTable bulk payment (spec 0211 AC-017)', () => {
  it('turns the selection on only while grouped by supplier', async () => {
    renderTable()
    const region = screen.getByRole('region', { name: 'table-purchase-invoice-installments' })
    expect(region).toHaveAttribute('data-bulk', 'false')

    fireEvent.click(screen.getByRole('button', { name: 'group-supplier' }))

    await waitFor(() => expect(region).toHaveAttribute('data-bulk', 'true'))
  })

  it('keeps the selection off without purchase-invoices.pay', async () => {
    canMock.mockReturnValue(false)
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'group-supplier' }))

    await waitFor(() =>
      expect(screen.getByRole('region', { name: 'table-purchase-invoice-installments' })).toHaveAttribute(
        'data-bulk',
        'false',
      ),
    )
    expect(canMock).toHaveBeenCalledWith('purchase-invoices.pay')
  })
})

describe('PurchaseInvoiceInstallmentsTable reconciliation lock and ?quick= (spec 0363 AC-016, AC-017)', () => {
  it('opens on the quick filter named by ?quick=overdue', () => {
    renderTable('/purchase-invoice-installments?quick=overdue')

    const region = screen.getByRole('region', { name: 'table-purchase-invoice-installments' })
    expect(JSON.parse(region.getAttribute('data-forced') ?? '{}')).toEqual({
      status: { filterType: 'set', values: ['unpaid'] },
      overdue: { filterType: 'set', values: ['yes'] },
    })
    expect(screen.getByRole('tab', { name: 'Overdue', selected: true })).toBeInTheDocument()
  })

  it('keeps the default for an unknown ?quick= value', () => {
    renderTable('/purchase-invoice-installments?quick=bogus')

    const region = screen.getByRole('region', { name: 'table-purchase-invoice-installments' })
    expect(JSON.parse(region.getAttribute('data-forced') ?? '{}')).toEqual({
      status: { filterType: 'set', values: ['unpaid'] },
    })
  })

  it('disables clear and record with the reason on a reconciled installment', () => {
    rowState.reconciled = true
    renderTable()

    expect(screen.getByRole('button', { name: /clear_payment - ticked on a bank statement/ })).toBeDisabled()
    expect(screen.getByRole('button', { name: /record_payment - ticked on a bank statement/ })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'open_document' })).toBeEnabled()
  })

  it('AC-013 disables clear payment with the reason on a compensated installment', () => {
    rowState.compensated = true
    renderTable()

    expect(screen.getByRole('button', { name: /clear_payment - compensated/ })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'record_payment' })).toBeEnabled()
  })

  it('AC-012 Compensate opens the dialog on the installment, with the supplier and company of its invoice', async () => {
    const detail = purchaseDetail()
    getMock.mockResolvedValue(detail)
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'compensate' }))

    const dialog = await screen.findByRole('dialog', { name: 'compensation dialog' })
    expect(JSON.parse(dialog.textContent ?? '{}')).toEqual({
      registryId: detail.purchase_invoice.supplier.id,
      companyId: detail.purchase_invoice.company.id,
      payableId: 7,
    })
  })
})
