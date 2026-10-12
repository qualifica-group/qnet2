import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { invoiceEditor } from '@/i18n/locales/en-invoice-editor'
import { invoices } from '@/i18n/locales/en-invoices'
import { InvoiceEditorDialog } from '@/features/invoices/invoice-editor-dialog'
import type {
  InstallmentPreviewPayload,
  InstallmentPreviewRow,
  Invoice,
  InvoiceWithPermissions,
} from '@/features/invoices/types'

const getInvoiceMock = vi.fn<(id: number) => Promise<InvoiceWithPermissions>>()
const previewMock = vi.fn<(payload: InstallmentPreviewPayload) => Promise<InstallmentPreviewRow[]>>()

vi.mock('@/features/invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/invoices/api')>()),
  getInvoice: (id: number) => getInvoiceMock(id),
  previewInstallments: (payload: InstallmentPreviewPayload) => previewMock(payload),
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

function invoiceWith(hasCollections: boolean): InvoiceWithPermissions {
  return {
    id: 5,
    number_label: '3/2026',
    document_date: '2026-10-06',
    company: { id: 3, name: 'Qualifica Srl' },
    customer: { id: 5, name: 'Acme Spa', vat_number: null },
    payment_method: { id: 2, name: 'Bonifico 30 gg' },
    financial_account: null,
    work_order: null,
    quote: null,
    proforma_request: null,
    notes: null,
    internal_note: null,
    tag: null,
    has_collections: hasCollections,
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
    permissions: { actions: {} },
  } as unknown as InvoiceWithPermissions
}

const REBALANCED: InstallmentPreviewRow[] = [
  { sequence: 1, due_date: '2026-11-05', amount: '400.00', payment_method_code: null, collected_amount: '400.00', locked: true },
  { sequence: 2, due_date: '2026-12-05', amount: '410.00', payment_method_code: null, collected_amount: null, locked: false },
  { sequence: 3, due_date: '2027-01-05', amount: '410.00', payment_method_code: null, collected_amount: null, locked: false },
]

function renderEditor() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <InvoiceEditorDialog
        mode="edit"
        invoiceId={5}
        open
        onClose={vi.fn()}
        onSaved={vi.fn<(invoice: Invoice) => void>()}
      />
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
})

describe('InvoiceEditorDialog with collections (spec 0196 AC-024)', () => {
  it('makes date, customer and payment read-only, sends invoice_id and shows collected installments as locked', async () => {
    getInvoiceMock.mockResolvedValue(invoiceWith(true))
    renderEditor()

    expect(await screen.findByLabelText(/Document date/)).toBeDisabled()
    expect(screen.getByRole('combobox', { name: /Bill to/ })).toBeDisabled()
    expect(screen.getByRole('combobox', { name: /Payment/ })).toBeDisabled()
    expect(screen.getByText(/cannot be changed/)).toBeInTheDocument()

    await waitFor(() => expect(previewMock).toHaveBeenCalled())
    expect(previewMock.mock.calls[0][0]).toMatchObject({ invoice_id: 5, total_amount: 1220 })
    expect(await screen.findByText('Collected 400,00 €', { exact: false })).toBeInTheDocument()
    expect(screen.getAllByText(/^Collected /)).toHaveLength(1)
  })

  it('keeps the fields editable and omits invoice_id without collections', async () => {
    getInvoiceMock.mockResolvedValue(invoiceWith(false))
    previewMock.mockResolvedValue([{ ...REBALANCED[1], sequence: 1 }])
    renderEditor()

    expect(await screen.findByLabelText(/Document date/)).toBeEnabled()
    expect(screen.getByRole('combobox', { name: /Bill to/ })).toBeEnabled()

    await waitFor(() => expect(previewMock).toHaveBeenCalled())
    expect(previewMock.mock.calls[0][0]).not.toHaveProperty('invoice_id')
  })
})
