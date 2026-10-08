import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { invoiceEditor } from '@/i18n/locales/en-invoice-editor'
import { InvoiceEditorDialog } from '@/features/invoices/invoice-editor-dialog'
import type { Invoice, InvoiceDraft, InstallmentPreviewRow } from '@/features/invoices/types'

const getDraftMock = vi.fn<(id: number) => Promise<InvoiceDraft>>()
const previewMock = vi.fn<() => Promise<InstallmentPreviewRow[]>>()
const createMock = vi.fn<(id: number, payload: unknown) => Promise<Invoice>>()

vi.mock('@/features/invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/invoices/api')>()),
  getInvoiceDraft: (id: number) => getDraftMock(id),
  previewInstallments: () => previewMock(),
  createInvoice: (id: number, payload: unknown) => createMock(id, payload),
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

const DRAFT: InvoiceDraft = {
  proforma_request: { id: 7, kind: 'consultancy', note: 'Please issue' },
  work_order: { id: 4, code: 'WO-2026-004', title: null },
  quote: { id: 9, code: 'Q-9' },
  defaults: {
    document_date: '2026-10-06',
    company: { id: 3, name: 'Qualifica Srl' },
    customer: { id: 5, name: 'Acme Spa' },
    payment_method: { id: 2, name: 'Bonifico 30 gg' },
    financial_account: null,
    layout: null,
    notes: null,
  },
  available_lines: [
    {
      quote_line_id: 11,
      product: { id: 1, name: 'Consulting' },
      description: 'Consulting day',
      quantity: '2.00',
      unit_price: '100.00',
      vat_rate: VAT,
      net_amount: '200.00',
      vat_amount: '44.00',
      total_amount: '244.00',
    },
    {
      quote_line_id: 12,
      product: { id: 2, name: 'Report' },
      description: 'Final report',
      quantity: '1.00',
      unit_price: '50.50',
      vat_rate: VAT,
      net_amount: '50.50',
      vat_amount: '11.11',
      total_amount: '61.61',
    },
  ],
  bank_accounts: [],
}

function renderDialog(onSaved = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <InvoiceEditorDialog mode="create" proformaRequestId={7} open onClose={vi.fn()} onSaved={onSaved} />
    </QueryClientProvider>,
  )
  return onSaved
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { invoiceEditor }, true, true)
})

beforeEach(() => {
  getDraftMock.mockReset().mockResolvedValue(DRAFT)
  previewMock.mockReset().mockResolvedValue([
    { sequence: 1, due_date: '2026-11-05', amount: '305.61', payment_method_code: null, collected_amount: null, locked: false },
  ])
  createMock.mockReset()
})

describe('InvoiceEditorDialog create (spec 0194 AC-009)', () => {
  it('prefills from the draft, adds available lines, updates totals and issues the payload', async () => {
    const issued = { id: 99, number_label: '1/2026' } as Invoice
    createMock.mockResolvedValue(issued)
    const onSaved = renderDialog()

    expect(await screen.findByRole('heading', { name: /Work order #WO-2026-004/ })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: /Issuing company/ })).toHaveTextContent('Qualifica Srl')
    expect(screen.getByRole('combobox', { name: /Customer/ })).toHaveTextContent('Acme Spa')
    expect(screen.getByRole('combobox', { name: /Payment/ })).toHaveTextContent('Bonifico 30 gg')

    fireEvent.click(screen.getByRole('button', { name: 'Add line: Consulting day' }))
    expect(screen.getAllByText('Added')).toHaveLength(1)
    const totals = screen.getByLabelText('Totals')
    expect(within(totals).getByText(/244,00/)).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Add all' }))
    expect(screen.getAllByText('Added')).toHaveLength(2)
    expect(within(totals).getByText(/305,61/)).toBeInTheDocument()
    expect(await screen.findByText('Due date')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Issue' }))

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    expect(createMock).toHaveBeenCalledWith(7, {
      document_date: '2026-10-06',
      company_id: 3,
      customer_registry_id: 5,
      payment_method_id: 2,
      financial_account_id: null,
      layout_id: null,
      notes: null,
      internal_note: null,
      tag: null,
      lines: [
        { quote_line_id: 11, product_id: 1, description: 'Consulting day', quantity: 2, unit_price: 100, vat_rate_id: 1 },
        { quote_line_id: 12, product_id: 2, description: 'Final report', quantity: 1, unit_price: 50.5, vat_rate_id: 1 },
      ],
    })
    await waitFor(() => expect(onSaved).toHaveBeenCalledWith(issued))
  })
})
