import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { InvoiceInstallmentsTable } from '@/features/invoice-installments/invoice-installments-table'
import type { InstallmentDetail } from '@/features/invoice-installments/types'
import type { Invoice, InvoiceInstallment } from '@/features/invoices/types'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

const refreshMock = vi.fn()
const getInstallmentMock = vi.fn<(id: number) => Promise<InstallmentDetail>>()
const getInvoiceMock = vi.fn<(id: number) => Promise<unknown>>()
const clearMock = vi.fn<(id: number) => Promise<Invoice>>()
const canMock = vi.fn<(permission: string) => boolean>()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/components/page-header', () => ({ PageHeader: () => <div /> }))
vi.mock('@/features/invoice-installments/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/invoice-installments/api')>()),
  getInstallment: (id: number) => getInstallmentMock(id),
}))
vi.mock('@/features/invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/invoices/api')>()),
  getInvoice: (id: number) => getInvoiceMock(id),
  clearCollection: (id: number) => clearMock(id),
}))
vi.mock('@/features/invoices/invoice-detail-sheet', () => ({
  InvoiceDetailSheet: ({ invoiceId }: { invoiceId: number | null }) =>
    invoiceId === null ? null : <div role="dialog" aria-label="invoice sheet">{`invoice ${invoiceId}`}</div>,
}))
vi.mock('@/features/invoice-installments/installment-edit-dialog', () => ({
  InstallmentEditDialog: ({ installmentId }: { installmentId: number | null }) =>
    installmentId === null ? null : <div role="dialog" aria-label="edit dialog">{`edit ${installmentId}`}</div>,
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function action(key: string): TableActionDefinition {
  return { key, label: `actions.${key}`, icon: 'eye', type: 'action', confirm: false }
}

const ROW: TableRow = { id: 21, actions: [] }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    {
      domain: string
      forcedFilterModel?: Record<string, unknown>
      onAction?: (action: TableActionDefinition, row: TableRow) => void
      renderFooter?: (aggregates: Record<string, number> | undefined) => ReactNode
      getBulkActions?: unknown
      onRowGroupColumnsChange?: (columnIds: string[]) => void
    }
  >(function TableViewStub({ domain, forcedFilterModel, onAction, renderFooter, getBulkActions, onRowGroupColumnsChange }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock, setFilterModel: () => {}, clearSelection: () => {} }))
    return (
      <div
        role="region"
        aria-label={`table-${domain}`}
        data-forced={JSON.stringify(forcedFilterModel)}
        data-bulk={String(getBulkActions !== undefined)}
      >
        <button type="button" onClick={() => onRowGroupColumnsChange?.(['customer'])}>
          group-customer
        </button>
        {['view_invoice', 'edit', 'record_collection', 'clear_collection'].map((key) => (
          <button key={key} type="button" onClick={() => onAction?.(action(key), ROW)}>
            {key}
          </button>
        ))}
        {renderFooter?.({ amount: 1200, collected_amount: 200, residual_amount: 1000 })}
      </div>
    )
  }),
}))

const OPEN: InvoiceInstallment = {
  id: 21,
  sequence: 1,
  due_date: '2026-11-05',
  amount: '400.00',
  payment_method_code: null,
  collected_amount: null,
  collected_at: null,
  status: 'unpaid',
}
const COLLECTED: InvoiceInstallment = {
  ...OPEN,
  id: 22,
  sequence: 2,
  collected_amount: '400.00',
  collected_at: '2026-11-06',
  status: 'paid',
}

function stubDetail(): InstallmentDetail {
  return {
    id: 21,
    sequence: 1,
    due_date: '2026-11-05',
    payment_method_code: null,
    status: 'unpaid',
    is_overdue: false,
    days_overdue: 0,
    invoice: {
      id: 3,
      number_label: '12/2026',
      document_date: '2026-10-01',
      total_amount: '800.00',
      customer: { id: 1, name: 'ACME Spa' },
      work_order: null,
      company_site: null,
      operational_site: null,
    },
    field_permissions: {},
    abilities: { update: true, collect: true, view_invoice: true },
  }
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <InvoiceInstallmentsTable />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  refreshMock.mockReset()
  getInstallmentMock.mockReset().mockResolvedValue(stubDetail())
  getInvoiceMock.mockReset().mockResolvedValue({ id: 3, installments: [OPEN, COLLECTED] })
  clearMock.mockReset().mockResolvedValue({} as Invoice)
  canMock.mockReset().mockReturnValue(true)
})

describe('InvoiceInstallmentsTable (spec 0197 AC-018, AC-020)', () => {
  it('mounts the installments domain forced to the not-collected filter and renders the footer totals', () => {
    renderTable()

    const region = screen.getByRole('region', { name: 'table-invoice-installments' })
    expect(JSON.parse(region.getAttribute('data-forced') ?? '{}')).toEqual({
      status: { filterType: 'set', values: ['unpaid'] },
    })
    expect(screen.getByRole('tab', { name: 'Not collected', selected: true })).toBeInTheDocument()
    expect(screen.getByRole('group', { name: 'Totals of the current filter' })).toHaveTextContent('Residual')
  })

  it('opens the invoice detail sheet from "view invoice" using the installment invoice reference', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'view_invoice' }))

    expect(await screen.findByRole('dialog', { name: 'invoice sheet' })).toHaveTextContent('invoice 3')
  })

  it('opens the edit dialog on the clicked installment', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'edit' }))

    expect(screen.getByRole('dialog', { name: 'edit dialog' })).toHaveTextContent('edit 21')
  })

  it('reuses the existing collection dialog for "record collection"', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'record_collection' }))

    expect(await screen.findByRole('dialog', { name: 'Record collection' })).toBeInTheDocument()
  })

  it('reuses the existing clear-collection confirm and refreshes the grid after confirming', async () => {
    getInvoiceMock.mockResolvedValue({ id: 3, installments: [{ ...COLLECTED, id: 21 }] })
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'clear_collection' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Clear collection' }))

    await waitFor(() => expect(clearMock).toHaveBeenCalledWith(21))
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
  })
})

describe('InvoiceInstallmentsTable bulk collection (spec 0198 AC-009)', () => {
  it('turns the selection on only while grouped by customer', async () => {
    renderTable()
    const region = screen.getByRole('region', { name: 'table-invoice-installments' })
    expect(region).toHaveAttribute('data-bulk', 'false')

    fireEvent.click(screen.getByRole('button', { name: 'group-customer' }))

    await waitFor(() => expect(region).toHaveAttribute('data-bulk', 'true'))
  })

  it('keeps the selection off without invoices.collect', async () => {
    canMock.mockReturnValue(false)
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'group-customer' }))

    await waitFor(() =>
      expect(screen.getByRole('region', { name: 'table-invoice-installments' })).toHaveAttribute('data-bulk', 'false'),
    )
    expect(canMock).toHaveBeenCalledWith('invoices.collect')
  })
})
