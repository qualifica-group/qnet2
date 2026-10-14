import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
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

const FOR_SELECT_ITEMS: Record<string, unknown[]> = {
  registries: [{ id: 8, label: 'Acme Supplies' }],
  companies: [{ id: 3, label: 'Qualifica Srl' }],
  'payment-methods': [{ id: 2, label: 'Bonifico 30 gg' }],
  'cost-types': [{ id: 1, label: 'Collaboratori', meta: { color: 'blue' } }],
  'work-orders': [{ id: 5, label: 'WO-5' }],
  'vat-rates': [{ id: 1, label: 'IVA 22%', meta: { rate: '22.00' } }],
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
const ordersMock = vi.fn<() => Promise<InvoiceableOrder[]>>()
const previewMock = vi.fn<() => Promise<PurchaseInstallmentPreviewRow[]>>()

vi.mock('@/features/purchase-invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoices/api')>()),
  createPurchaseInvoice: (payload: PurchaseInvoiceWritePayload) => createMock(payload),
  fetchPurchaseInvoiceDraft: (orderIds: readonly number[]) => draftMock(orderIds),
  fetchInvoiceableOrders: () => ordersMock(),
  previewPurchaseInvoiceInstallments: () => previewMock(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const VAT = { id: 1, name: 'IVA 22%', rate: '22.00' }
const COST_TYPE = { id: 1, name: 'Collaboratori', color: 'blue' }

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
      product: { id: 1, name: 'Consulting' },
      description: 'Consulting day',
      quantity: '2.500',
      unit_price: '100.00',
      vat_rate: VAT,
      cost_type: COST_TYPE,
      work_order: { id: 5, code: 'WO-5', title: null },
      net_amount: '250.00',
      vat_amount: '55.00',
      total_amount: '305.00',
    },
    {
      id: null,
      sort_order: 72,
      purchase_order_line_id: 72,
      purchase_order: { id: 7, code: 'ODA-7' },
      product: null,
      description: 'Travel',
      quantity: '1.000',
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
  const onSaved = vi.fn()
  render(
    <QueryClientProvider client={client}>
      <PurchaseInvoiceEditorDialog mode="create" open onOpenChange={vi.fn()} onSaved={onSaved} {...props} />
    </QueryClientProvider>,
  )
  return onSaved
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { purchaseInvoices }, true, true)
})

beforeEach(() => {
  vi.clearAllMocks()
  createMock.mockReset()
  draftMock.mockReset().mockResolvedValue(DRAFT)
  ordersMock.mockReset().mockResolvedValue([])
  previewMock.mockReset().mockResolvedValue([
    { sequence: 1, due_date: '2026-11-12', amount: '305.00', payment_method_code: null },
  ])
})

describe('PurchaseInvoiceEditorDialog create from scratch (spec 0250 AC-012)', () => {
  it('adds and removes rows with cost type and work order, previews amounts and installments, and saves', async () => {
    const saved = { id: 40 } as PurchaseInvoice
    createMock.mockResolvedValue(saved)
    const onSaved = renderDialog()

    fireEvent.change(screen.getByLabelText(/Supplier document number/), { target: { value: 'FA-1' } })
    fireEvent.change(screen.getByLabelText(/Document date/), { target: { value: '2026-10-13' } })
    await pick(/^Supplier$/, /Acme/)
    await pick(/^Company$/, /Qualifica/)
    await pick(/^Payment method$/, /Bonifico/)
    await pick(/Default cost type/, /Collaboratori/)

    fireEvent.click(screen.getByRole('button', { name: 'Free' }))
    fireEvent.change(screen.getByLabelText('Free row description'), { target: { value: 'Consulting' } })
    fireEvent.click(screen.getByRole('button', { name: 'Add row' }))
    fireEvent.change(screen.getByLabelText('Free row description'), { target: { value: 'To drop' } })
    fireEvent.click(screen.getByRole('button', { name: 'Add row' }))
    expect(screen.getByLabelText(/^Description 2/)).toHaveValue('To drop')
    fireEvent.click(screen.getByRole('button', { name: 'Remove row 2' }))
    expect(screen.queryByLabelText(/^Description 2/)).not.toBeInTheDocument()

    fireEvent.change(screen.getByLabelText('Quantity 1'), { target: { value: '2.5' } })
    fireEvent.change(screen.getByLabelText('Unit price 1'), { target: { value: '100' } })
    await pick(/^VAT 1$/, /IVA 22%/)
    await pick(/^Work order 1$/, /WO-5/)

    const totals = screen.getByLabelText('Totals')
    expect(within(totals).getAllByText(/305,00/).length).toBeGreaterThan(0)
    expect(await screen.findByText('Due date')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    expect(createMock).toHaveBeenCalledWith({
      document_type: 'invoice',
      document_number: 'FA-1',
      document_date: '2026-10-13',
      company_id: 3,
      supplier_id: 8,
      payment_method_id: 2,
      financial_account_id: null,
      company_site_id: null,
      operational_site_id: null,
      cost_type_id: 1,
      work_order_id: null,
      notes: null,
      purchase_order_ids: [],
      lines: [
        {
          purchase_order_line_id: null,
          product_id: null,
          description: 'Consulting',
          quantity: 2.5,
          unit_price: 100,
          vat_rate_id: 1,
          cost_type_id: 1,
          work_order_id: 5,
        },
      ],
    })
    await waitFor(() => expect(onSaved).toHaveBeenCalledWith(saved))
  })

  it('requires a cost type on every row before sending', async () => {
    renderDialog()

    fireEvent.change(screen.getByLabelText(/Supplier document number/), { target: { value: 'FA-1' } })
    await pick(/^Supplier$/, /Acme/)
    await pick(/^Company$/, /Qualifica/)
    await pick(/^Payment method$/, /Bonifico/)
    fireEvent.click(screen.getByRole('button', { name: 'Free' }))
    fireEvent.change(screen.getByLabelText('Free row description'), { target: { value: 'Consulting' } })
    fireEvent.click(screen.getByRole('button', { name: 'Add row' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('The cost type is required.')).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()
  })
})

describe('PurchaseInvoiceEditorDialog register from purchase orders (spec 0250 AC-012)', () => {
  it('opens when the draft has no payment method, leaving it to the user and blocking save until picked', async () => {
    draftMock.mockResolvedValue({ ...DRAFT, defaults: { ...DRAFT.defaults, payment_method: null } })
    createMock.mockResolvedValue({ id: 42 } as PurchaseInvoice)
    renderDialog({ purchaseOrderIds: [7] })

    expect(await screen.findByLabelText(/^Description 1/)).toHaveValue('Consulting day')
    expect(screen.getByRole('combobox', { name: /^Payment method$/ })).toHaveTextContent('Select the payment method')

    fireEvent.change(screen.getByLabelText(/Supplier document number/), { target: { value: 'FA-9' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))
    expect(await screen.findByText('The payment method is required.')).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()

    await pick(/^Payment method$/, /Bonifico/)
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))
    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    expect(createMock.mock.calls[0]?.[0].payment_method_id).toBe(2)
  })

  it('prefills the rows from the ODA draft and sends the link and the order row references', async () => {
    createMock.mockResolvedValue({ id: 41 } as PurchaseInvoice)
    renderDialog({ purchaseOrderIds: [7] })

    expect(await screen.findByLabelText(/^Description 1/)).toHaveValue('Consulting day')
    expect(screen.getByLabelText(/^Description 2/)).toHaveValue('Travel')
    expect(screen.getByLabelText('Quantity 1')).toHaveValue(2.5)
    expect(draftMock).toHaveBeenCalledWith([7])
    expect(screen.getByRole('combobox', { name: /^Supplier$/ })).toBeDisabled()
    expect(within(screen.getByLabelText('Totals')).getAllByText(/355,00/).length).toBeGreaterThan(0)

    fireEvent.change(screen.getByLabelText(/Supplier document number/), { target: { value: 'FA-9' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    const payload = createMock.mock.calls[0]?.[0]
    expect(payload?.purchase_order_ids).toEqual([7])
    expect(payload?.lines.map((line) => line.purchase_order_line_id)).toEqual([71, 72])
    expect(payload?.lines[1]).toMatchObject({ vat_rate_id: null, cost_type_id: 1, quantity: 1 })
  })

  it('adds an invoiceable ODA chosen in the editor, linking it and appending its rows', async () => {
    ordersMock.mockResolvedValue([
      { id: 7, code: 'ODA-7', order_date: '2026-10-01', grand_total: '355.00', status: 'approved' },
    ])
    renderDialog()

    await pick(/^Supplier$/, /Acme/)
    await pick(/^Company$/, /Qualifica/)
    fireEvent.click(await screen.findByRole('button', { name: 'Add the rows of order ODA-7' }))

    expect(await screen.findByLabelText(/^Description 2/)).toHaveValue('Travel')
    expect(draftMock).toHaveBeenCalledWith([7])
    expect(screen.getByText('Added')).toBeInTheDocument()
  })
})

describe('PurchaseInvoiceEditorDialog server errors (spec 0250 AC-012)', () => {
  it('puts 422 messages on their inputs, including row fields, with the accessible error triad', async () => {
    createMock.mockRejectedValue(
      validationError({
        document_number: ['Number already used.'],
        'lines.0.cost_type_id': ['Cost type not allowed.'],
        'lines.1.purchase_order_line_id': ['Already invoiced.'],
        purchase_order_ids: ['Orders of different suppliers.'],
      }),
    )
    renderDialog({ purchaseOrderIds: [7] })

    fireEvent.change(await screen.findByLabelText(/Supplier document number/), { target: { value: 'FA-9' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const numberAlert = await screen.findByText('Number already used.')
    const numberInput = screen.getByLabelText(/Supplier document number/)
    expect(numberAlert).toHaveAttribute('role', 'alert')
    expect(numberInput).toHaveAttribute('aria-invalid', 'true')
    expect(numberInput.getAttribute('aria-describedby')).toContain(numberAlert.id)

    expect(screen.getByText('Cost type not allowed.')).toHaveAttribute('role', 'alert')
    const invoicedAlert = screen.getByText('Already invoiced.')
    expect(screen.getByLabelText(/^Description 2/).getAttribute('aria-describedby')).toContain(invoicedAlert.id)
    expect(screen.getByText('Orders of different suppliers.')).toHaveAttribute('role', 'alert')
  })
})
