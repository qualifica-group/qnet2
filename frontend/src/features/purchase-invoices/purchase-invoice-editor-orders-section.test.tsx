import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { purchaseInvoices } from '@/i18n/locales/en-purchase-invoices'
import { PurchaseInvoiceEditorOrdersSection } from '@/features/purchase-invoices/purchase-invoice-editor-orders-section'
import type { InvoiceableOrder, PurchaseInvoiceDraft } from '@/features/purchase-invoices/types'

const draftMock = vi.fn<(orderIds: readonly number[]) => Promise<PurchaseInvoiceDraft>>()
const ordersMock = vi.fn<() => Promise<InvoiceableOrder[]>>()

vi.mock('@/features/purchase-invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoices/api')>()),
  fetchPurchaseInvoiceDraft: (orderIds: readonly number[]) => draftMock(orderIds),
  fetchInvoiceableOrders: () => ordersMock(),
}))

const ORDERS: InvoiceableOrder[] = [
  { id: 7, code: 'ODA-7', order_date: '2026-10-01', grand_total: '355.00', status: 'approved' },
  { id: 9, code: 'ODA-9', order_date: '2026-09-21', grand_total: '480.00', status: 'fulfilled' },
]

const DRAFT: PurchaseInvoiceDraft = {
  defaults: {
    company: { id: 3, name: 'Qualifica Srl' },
    supplier: { id: 8, name: 'Acme Supplies', label: 'Acme Supplies' },
    payment_method: null,
    company_site: null,
    operational_site: null,
    cost_type: null,
    work_order: null,
  },
  purchase_order_ids: [7],
  lines: [
    {
      id: null,
      sort_order: 0,
      purchase_order_line_id: 71,
      purchase_order: { id: 7, code: 'ODA-7' },
      product: null,
      description: 'Consulting day',
      quantity: '2.500',
      available_quantity: '2.500',
      ordered_quantity: '4.000',
      unit_price: '100.00',
      vat_rate: null,
      cost_type: { id: 1, name: 'Collaboratori', color: 'blue' },
      work_order: null,
      net_amount: '250.00',
      vat_amount: '0.00',
      total_amount: '250.00',
    },
  ],
}

function renderSection(linkedOrderIds: number[] = []) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const onAdd = vi.fn()
  render(
    <QueryClientProvider client={client}>
      <PurchaseInvoiceEditorOrdersSection
        supplierId={8}
        companyId={3}
        linkedOrderIds={linkedOrderIds}
        isAdding={false}
        notice={null}
        onAdd={onAdd}
      />
    </QueryClientProvider>,
  )
  return { onAdd }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { purchaseInvoices }, true, true)
})

beforeEach(() => {
  draftMock.mockReset().mockResolvedValue(DRAFT)
  ordersMock.mockReset().mockResolvedValue(ORDERS)
})

describe('PurchaseInvoiceEditorOrdersSection', () => {
  it('lists the invoiceable orders collapsed, without loading their rows', async () => {
    renderSection()

    expect(await screen.findByRole('button', { name: 'Show the rows of order ODA-7' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Show the rows of order ODA-9' })).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
    expect(draftMock).not.toHaveBeenCalled()
  })

  it('expands an order to its billable rows with ordered and to-invoice quantities, then hides them', async () => {
    renderSection()

    fireEvent.click(await screen.findByRole('button', { name: 'Show the rows of order ODA-7' }))

    const table = await screen.findByRole('table', { name: 'Rows of order ODA-7' })
    expect(draftMock).toHaveBeenCalledWith([7])
    const cells = Array.from(table.querySelectorAll('tbody td')).map((cell) => cell.textContent)
    expect(cells).toEqual(['Consulting day', '4.000', '2.500', expect.stringContaining('100,00')])

    fireEvent.click(screen.getByRole('button', { name: 'Hide the rows of order ODA-7' }))
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('keeps the Add action next to the toggle and marks linked orders as added', async () => {
    const { onAdd } = renderSection([9])

    fireEvent.click(await screen.findByRole('button', { name: 'Add the rows of order ODA-7' }))
    expect(onAdd).toHaveBeenCalledWith(7)
    expect(screen.queryByRole('button', { name: 'Add the rows of order ODA-9' })).not.toBeInTheDocument()
    expect(screen.getByText('Added')).toBeInTheDocument()
  })
})
