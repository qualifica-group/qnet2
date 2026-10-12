import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { invoiceEditor } from '@/i18n/locales/en-invoice-editor'
import { invoices } from '@/i18n/locales/en-invoices'
import { InvoiceDetailView } from '@/features/invoices/invoice-detail'
import { openRow, pressRowButton, queryPencil } from '@/features/quotes/quote-test-helpers'
import type { FieldPermission } from '@/features/authorization/types'
import type {
  InstallmentPreviewPayload,
  InstallmentPreviewRow,
  InvoiceDetailsPayload,
  InvoiceWithPermissions,
  InvoiceWritePayload,
} from '@/features/invoices/types'

/**
 * Spec 0224: no edit dialog, a pencil per editable row. The rows that leave
 * the schedule alone save through PATCH details with their own keys only;
 * date, customer, payment and lines through the full PUT. With collections
 * the frozen rows offer no edit (spec 0196 D-1) and the lines editor shows
 * the rebalanced plan (AC-024).
 */

const getInvoiceMock = vi.fn<(id: number) => Promise<InvoiceWithPermissions>>()
const previewMock = vi.fn<(payload: InstallmentPreviewPayload) => Promise<InstallmentPreviewRow[]>>()
const updateMock = vi.fn<(id: number, payload: InvoiceWritePayload) => Promise<InvoiceWithPermissions>>()
const detailsMock = vi.fn<(id: number, payload: InvoiceDetailsPayload) => Promise<InvoiceWithPermissions>>()

vi.mock('@/features/invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/invoices/api')>()),
  getInvoice: (id: number) => getInvoiceMock(id),
  previewInstallments: (payload: InstallmentPreviewPayload) => previewMock(payload),
  updateInvoice: (id: number, payload: InvoiceWritePayload) => updateMock(id, payload),
  updateInvoiceDetails: (id: number, payload: InvoiceDetailsPayload) => detailsMock(id, payload),
}))

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn().mockResolvedValue({
    items: [],
    export_link: null,
    pagination: { total: 0, offset: 0, limit: 20, total_pages: 0 },
  }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const VAT = { id: 1, name: 'IVA 22%', rate: '22.00' }

const READ_ONLY_FIELD: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
}

const INVOICE_FIELDS = ['document_date', 'customer_registry_id', 'payment_method_id', 'notes', 'tag', 'external_number']

function invoiceWith(hasCollections: boolean, readOnly = false): InvoiceWithPermissions {
  return {
    id: 5,
    type: 'proforma',
    number_label: '3/2026',
    document_date: '2026-10-06',
    company: { id: 3, name: 'Qualifica Srl' },
    customer: { id: 5, name: 'Acme Spa', vat_number: null },
    payment_method: { id: 2, name: 'Bonifico 30 gg' },
    financial_account: null,
    layout: null,
    work_order: null,
    quote: null,
    proforma_request: null,
    net_amount: '1000.00',
    vat_amount: '220.00',
    total_amount: '1220.00',
    collected_amount: '0.00',
    residual_amount: '1220.00',
    payment_status: 'pending',
    external_number: null,
    external_date: null,
    notes: null,
    internal_note: null,
    tag: null,
    deviation: null,
    has_collections: hasCollections,
    created_by: { id: 1, name: 'Admin' },
    created_at: '2026-10-06T10:00:00Z',
    updated_at: '2026-10-06T10:00:00Z',
    lines: [
      {
        id: 1,
        quote_line_id: null,
        product: null,
        description: 'Consulting',
        quantity: '1.00',
        unit_price: '1000.00',
        vat_rate: VAT,
        net_amount: '1000.00',
        vat_amount: '220.00',
        total_amount: '1220.00',
        sort_order: 1,
      },
    ],
    installments: [],
    permissions: {
      resource: { view: true, create: true, update: !readOnly, delete: true, export: true, import: false },
      fields: readOnly ? Object.fromEntries(INVOICE_FIELDS.map((field) => [field, READ_ONLY_FIELD])) : {},
      actions: { collect: true },
    },
  } as unknown as InvoiceWithPermissions
}

const REBALANCED: InstallmentPreviewRow[] = [
  { sequence: 1, due_date: '2026-11-05', amount: '400.00', payment_method_code: null, collected_amount: '400.00', locked: true },
  { sequence: 2, due_date: '2026-12-05', amount: '820.00', payment_method_code: null, collected_amount: null, locked: false },
]

function renderDetail() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <InvoiceDetailView invoiceId={5} onChanged={vi.fn()} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { invoiceEditor, invoices }, true, true)
})

beforeEach(() => {
  getInvoiceMock.mockReset()
  previewMock.mockReset().mockResolvedValue(REBALANCED)
  updateMock.mockReset().mockImplementation(() => Promise.resolve(invoiceWith(false)))
  detailsMock.mockReset().mockImplementation(() => Promise.resolve(invoiceWith(false)))
})

describe('InvoiceDetailView in-place editing (spec 0224)', () => {
  it('saves a details row through PATCH details with its own key only', async () => {
    getInvoiceMock.mockResolvedValue(invoiceWith(false))
    renderDetail()

    await screen.findByText('Consulting')
    openRow('Notes')
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'Pay by the 10th' } })
    pressRowButton('Save')

    await waitFor(() => expect(detailsMock).toHaveBeenCalledTimes(1))
    expect(detailsMock).toHaveBeenCalledWith(5, { notes: 'Pay by the 10th' })
    expect(updateMock).not.toHaveBeenCalled()
  })

  it('saves the external reference as number and date together', async () => {
    getInvoiceMock.mockResolvedValue(invoiceWith(false))
    renderDetail()

    await screen.findByText('Consulting')
    openRow('External invoice')
    fireEvent.change(screen.getByRole('textbox', { name: 'External invoice number' }), { target: { value: 'FT-9' } })
    pressRowButton('Save')
    expect(await screen.findByText('The invoice date is required together with the number.')).toBeInTheDocument()
    expect(detailsMock).not.toHaveBeenCalled()

    fireEvent.change(screen.getByLabelText('External invoice date'), { target: { value: '2026-10-20' } })
    pressRowButton('Save')

    await waitFor(() => expect(detailsMock).toHaveBeenCalledWith(5, { external_number: 'FT-9', external_date: '2026-10-20' }))
  })

  it('saves a document row through the full PUT, previewing the schedule while it is open', async () => {
    getInvoiceMock.mockResolvedValue(invoiceWith(false))
    previewMock.mockResolvedValue([{ ...REBALANCED[1], sequence: 1, collected_amount: null }])
    renderDetail()

    await screen.findByText('Consulting')
    expect(previewMock).not.toHaveBeenCalled()
    openRow('Document date')
    fireEvent.change(screen.getByLabelText(/^Document date/), { target: { value: '2026-10-08' } })

    await waitFor(() => expect(previewMock).toHaveBeenCalled())
    expect(previewMock.mock.calls.at(-1)?.[0]).not.toHaveProperty('invoice_id')
    pressRowButton('Save')

    await waitFor(() => expect(updateMock).toHaveBeenCalledTimes(1))
    expect(updateMock.mock.calls[0][1]).toMatchObject({
      document_date: '2026-10-08',
      company_id: 3,
      customer_registry_id: 5,
      payment_method_id: 2,
      lines: [{ description: 'Consulting', quantity: 1, unit_price: 1000, vat_rate_id: 1 }],
    })
    expect(updateMock.mock.calls[0][1]).not.toHaveProperty('external_number')
    expect(detailsMock).not.toHaveBeenCalled()
  })

  it('cancels without any request', async () => {
    getInvoiceMock.mockResolvedValue(invoiceWith(false))
    renderDetail()

    await screen.findByText('Consulting')
    openRow('Notes')
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'Draft' } })
    pressRowButton('Cancel')

    expect(screen.queryByRole('textbox', { name: 'Notes' })).not.toBeInTheDocument()
    expect(detailsMock).not.toHaveBeenCalled()
    expect(updateMock).not.toHaveBeenCalled()
  })

  it('freezes date, customer and payment with collections and shows the rebalanced plan in the lines editor (spec 0196 AC-024)', async () => {
    getInvoiceMock.mockResolvedValue(invoiceWith(true))
    renderDetail()

    await screen.findByText('Consulting')
    expect(screen.getByText(/cannot be changed/)).toBeInTheDocument()
    expect(queryPencil('Document date')).toBeNull()
    expect(queryPencil('Bill to')).toBeNull()
    expect(queryPencil('Payment')).toBeNull()
    expect(queryPencil('Issuing company')).toBeNull()

    openRow('Lines')
    await waitFor(() => expect(previewMock).toHaveBeenCalled())
    expect(previewMock.mock.calls[0][0]).toMatchObject({ invoice_id: 5, total_amount: 1220 })
    expect(await screen.findByText('Collected 400,00 €', { exact: false })).toBeInTheDocument()
  })

  it('offers no edit without the permission', async () => {
    getInvoiceMock.mockResolvedValue(invoiceWith(false, true))
    renderDetail()

    await screen.findByText('Consulting')
    expect(queryPencil('Notes')).toBeNull()
    expect(queryPencil('Document date')).toBeNull()
    expect(queryPencil('External invoice')).toBeNull()
  })
})
