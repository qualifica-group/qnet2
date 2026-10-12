import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { invoiceEditor } from '@/i18n/locales/en-invoice-editor'
import { invoices } from '@/i18n/locales/en-invoices'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { InvoiceEditorDialog } from '@/features/invoices/invoice-editor-dialog'
import { openRow, pressRowButton, rowValue } from '@/features/quotes/quote-test-helpers'
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

function renderDialog(onSaved = vi.fn(), onClose = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <InvoiceEditorDialog proformaRequestId={7} open onClose={onClose} onSaved={onSaved} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
  return onSaved
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { invoiceEditor, invoices }, true, true)
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

    // REQUIREMENT CHANGED (spec 0224 D-5): the draft opens on CLOSED rows, like the detail.
    expect(await screen.findByRole('heading', { name: /Work order #WO-2026-004/ })).toBeInTheDocument()
    expect(rowValue('Issuing company')).toBe('Qualifica Srl')
    expect(rowValue('Bill to')).toBe('Acme Spa')
    expect(rowValue('Payment')).toBe('Bonifico 30 gg')
    expect(screen.queryByRole('combobox', { name: /Bill to/ })).not.toBeInTheDocument()

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

  it('keeps a row edit in the draft on "Done" and sends it with the issue', async () => {
    createMock.mockResolvedValue({ id: 99, number_label: '1/2026' } as Invoice)
    renderDialog()

    fireEvent.click(await screen.findByRole('button', { name: 'Add all' }))
    openRow('Notes')
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'Pay by the 10th' } })
    pressRowButton('Done')
    expect(rowValue('Notes')).toBe('Pay by the 10th')

    fireEvent.click(screen.getByRole('button', { name: 'Issue' }))

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    expect(createMock.mock.calls[0][1]).toMatchObject({ notes: 'Pay by the 10th' })
  })

  it('asks before dropping a changed draft', async () => {
    const onClose = vi.fn()
    renderDialog(vi.fn(), onClose)

    fireEvent.click(await screen.findByRole('button', { name: 'Add all' }))
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(await screen.findByText('Leave without issuing?')).toBeInTheDocument()
    expect(onClose).not.toHaveBeenCalled()
    fireEvent.click(screen.getByRole('button', { name: 'Leave without issuing' }))
    await waitFor(() => expect(onClose).toHaveBeenCalledTimes(1))
  })
})
