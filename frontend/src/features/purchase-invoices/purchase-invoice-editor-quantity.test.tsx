import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { purchaseInvoices } from '@/i18n/locales/en-purchase-invoices'
import { PurchaseInvoiceEditorDialog } from '@/features/purchase-invoices/purchase-invoice-editor-dialog'
import type {
  InvoiceableOrder,
  PurchaseInstallmentPreviewRow,
  PurchaseInvoice,
  PurchaseInvoiceDraft,
  PurchaseInvoiceWritePayload,
} from '@/features/purchase-invoices/types'

/** Spec 0254 D-2: billing by quantity in the passive invoice editor (AC-014). */

const FOR_SELECT_ITEMS: Record<string, unknown[]> = {
  registries: [{ id: 8, label: 'Acme Supplies' }],
  companies: [{ id: 3, label: 'Qualifica Srl' }],
  'payment-methods': [{ id: 2, label: 'Bonifico 30 gg' }],
}

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn(async (resource: string) => ({
    items: FOR_SELECT_ITEMS[resource] ?? [],
    export_link: null,
    pagination: { total: 0, offset: 0, limit: 25, total_pages: 1 },
  })),
}))

const createMock = vi.fn<(payload: PurchaseInvoiceWritePayload) => Promise<PurchaseInvoice>>()
const draftMock = vi.fn<(orderIds: readonly number[]) => Promise<PurchaseInvoiceDraft>>()
const ordersMock = vi.fn<(companyId: number, supplierId: number) => Promise<InvoiceableOrder[]>>()
const previewMock = vi.fn<() => Promise<PurchaseInstallmentPreviewRow[]>>()

vi.mock('@/features/purchase-invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoices/api')>()),
  createPurchaseInvoice: (payload: PurchaseInvoiceWritePayload) => createMock(payload),
  fetchPurchaseInvoiceDraft: (orderIds: readonly number[]) => draftMock(orderIds),
  fetchInvoiceableOrders: (supplierId: number, companyId: number) => ordersMock(companyId, supplierId),
  previewPurchaseInvoiceInstallments: () => previewMock(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const COST_TYPE = { id: 1, name: 'Collaboratori', color: 'blue' }
const ORDER: InvoiceableOrder = { id: 7, code: 'ODA-7', order_date: '2026-10-01', grand_total: '355.00', status: 'approved' }

const DRAFT: PurchaseInvoiceDraft = {
  defaults: {
    company: { id: 3, name: 'Qualifica Srl' },
    supplier: { id: 8, name: 'Acme Supplies' },
    payment_method: { id: 2, name: 'Bonifico 30 gg' },
    company_site: null,
    operational_site: null,
    cost_type: null,
    work_order: null,
  },
  purchase_order_ids: [7],
  lines: [
    {
      id: null,
      sort_order: 71,
      purchase_order_line_id: 71,
      purchase_order: { id: 7, code: 'ODA-7' },
      product: null,
      description: 'Consulting day',
      quantity: '2.000',
      available_quantity: '2.500',
      unit_price: '100.00',
      vat_rate: null,
      cost_type: COST_TYPE,
      work_order: null,
      net_amount: '200.00',
      vat_amount: '0.00',
      total_amount: '200.00',
    },
    {
      id: null,
      sort_order: 72,
      purchase_order_line_id: 72,
      purchase_order: { id: 7, code: 'ODA-7' },
      product: null,
      description: 'Travel',
      quantity: '1.000',
      available_quantity: '1.000',
      unit_price: '50.00',
      vat_rate: null,
      cost_type: COST_TYPE,
      work_order: null,
      net_amount: '50.00',
      vat_amount: '0.00',
      total_amount: '50.00',
    },
  ],
}

function renderDialog(props: Partial<React.ComponentProps<typeof PurchaseInvoiceEditorDialog>> = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <PurchaseInvoiceEditorDialog mode="create" open onOpenChange={vi.fn()} onSaved={vi.fn()} {...props} />
    </QueryClientProvider>,
  )
}

async function pick(triggerName: RegExp, optionName: RegExp) {
  fireEvent.click(screen.getByRole('combobox', { name: triggerName }))
  fireEvent.click(await screen.findByRole('option', { name: optionName }))
  await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument())
}

function validationError(errors: Record<string, string[]>) {
  const response = { status: 422, data: { message: 'Invalid', errors } } as AxiosResponse
  return new AxiosError('Invalid', 'ERR_BAD_REQUEST', undefined, undefined, response)
}

async function openFromOrder() {
  renderDialog({ purchaseOrderIds: [7] })
  expect(await screen.findByLabelText(/^Description 1/)).toHaveValue('Consulting day')
  fireEvent.change(screen.getByLabelText(/Supplier document number/), { target: { value: 'FA-9' } })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { purchaseInvoices }, true, true)
})

beforeEach(() => {
  vi.clearAllMocks()
  createMock.mockReset()
  draftMock.mockReset().mockResolvedValue(DRAFT)
  ordersMock.mockReset().mockResolvedValue([ORDER])
  previewMock.mockReset().mockResolvedValue([])
})

describe('PurchaseInvoiceEditorDialog billing by quantity (spec 0254 D-2)', () => {
  it('requests the draft without any document type, even with Proforma selected', async () => {
    renderDialog()

    await pick(/^Document type$/, /^Proforma$/)
    await pick(/^Supplier$/, /Acme/)
    await pick(/^Company$/, /Qualifica/)
    fireEvent.click(await screen.findByRole('button', { name: 'Add the rows of order ODA-7' }))

    await waitFor(() => expect(draftMock).toHaveBeenCalledTimes(1))
    expect(draftMock).toHaveBeenCalledWith([7])
    expect(ordersMock).toHaveBeenCalledWith(3, 8)
  })

  it('does not refetch invoiceable orders nor drafts when the document type changes, and keeps the rows', async () => {
    await openFromOrder()
    fireEvent.click(screen.getByRole('button', { name: 'Free' }))
    fireEvent.change(screen.getByLabelText('Free row description'), { target: { value: 'Extra' } })
    fireEvent.click(screen.getByRole('button', { name: 'Add row' }))
    const draftCalls = draftMock.mock.calls.length
    const ordersCalls = ordersMock.mock.calls.length

    await pick(/^Document type$/, /^Proforma$/)

    expect(draftMock).toHaveBeenCalledTimes(draftCalls)
    expect(ordersMock).toHaveBeenCalledTimes(ordersCalls)
    expect(screen.getByLabelText('Quantity 1')).toHaveValue(2)
    expect(screen.getByLabelText(/^Description 1/)).toHaveValue('Consulting day')
    expect(screen.getByLabelText(/^Description 3/)).toHaveValue('Extra')
  })

  it('announces the receipt registration only for an Invoice with rows from an ODA', async () => {
    await openFromOrder()
    const notice = /Saving as an Invoice will register the receipt/
    expect(screen.getByRole('note')).toHaveTextContent(notice)

    await pick(/^Document type$/, /^Proforma$/)
    expect(screen.queryByRole('note')).not.toBeInTheDocument()
  })

  it('does not announce the receipt registration for an Invoice with free rows only', async () => {
    renderDialog()
    fireEvent.click(await screen.findByRole('button', { name: 'Free' }))
    fireEvent.change(screen.getByLabelText('Free row description'), { target: { value: 'Extra' } })
    fireEvent.click(screen.getByRole('button', { name: 'Add row' }))

    expect(screen.getByLabelText(/^Description 1/)).toHaveValue('Extra')
    expect(screen.queryByRole('note')).not.toBeInTheDocument()
  })

  it('explains an empty draft when adding an order', async () => {
    draftMock.mockResolvedValue({ ...DRAFT, lines: [] })
    renderDialog()

    await pick(/^Supplier$/, /Acme/)
    await pick(/^Company$/, /Qualifica/)
    fireEvent.click(await screen.findByRole('button', { name: 'Add the rows of order ODA-7' }))

    expect(await screen.findByText(/Nothing left to bill on the order/)).toBeInTheDocument()
    expect(screen.queryByLabelText(/^Description 1/)).not.toBeInTheDocument()
  })

  it('shows the billable maximum next to the quantity of an order row only', async () => {
    await openFromOrder()

    expect(screen.getByText('max 2.5')).toBeInTheDocument()
    expect(screen.getByText('max 1')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Free' }))
    fireEvent.change(screen.getByLabelText('Free row description'), { target: { value: 'Extra' } })
    fireEvent.click(screen.getByRole('button', { name: 'Add row' }))
    expect(screen.getAllByText(/^max /)).toHaveLength(2)
  })

  it('blocks a quantity above the available one with the accessible error triad and sends nothing', async () => {
    await openFromOrder()

    fireEvent.change(screen.getByLabelText('Quantity 1'), { target: { value: '3' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const alert = await screen.findByText(/exceeds the billable quantity of the order row \(available 2\.5\)/)
    const input = screen.getByLabelText('Quantity 1')
    expect(alert).toHaveAttribute('role', 'alert')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input.getAttribute('aria-describedby')).toContain(alert.id)
    expect(createMock).not.toHaveBeenCalled()
  })

  it('accepts the exact available quantity and does not send the client-only limit', async () => {
    createMock.mockResolvedValue({ id: 50 } as PurchaseInvoice)
    await openFromOrder()

    fireEvent.change(screen.getByLabelText('Quantity 1'), { target: { value: '2.5' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    const lines = createMock.mock.calls[0]?.[0].lines
    expect(lines?.[0]).toMatchObject({ purchase_order_line_id: 71, quantity: 2.5 })
    expect(lines?.[0]).not.toHaveProperty('available_quantity')
  })

  it('puts the server lines.N.quantity error on the quantity of that row with the accessible triad', async () => {
    createMock.mockRejectedValue(
      validationError({ 'lines.1.quantity': ["supera la quantita' fatturabile della riga ODA (disponibile 0.500)"] }),
    )
    await openFromOrder()

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const alert = await screen.findByText(/supera la quantita' fatturabile/)
    expect(alert).toHaveAttribute('role', 'alert')
    expect(screen.getByLabelText('Quantity 2')).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByLabelText('Quantity 2').getAttribute('aria-describedby')).toContain(alert.id)
    expect(screen.getByLabelText('Quantity 1')).not.toHaveAttribute('aria-invalid', 'true')
  })
})
