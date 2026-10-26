import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import i18n from '@/i18n'
import { InvoiceDetailView } from '@/features/invoices/invoice-detail'
import type { InvoiceWithPermissions } from '@/features/invoices/types'

// The pickers render the quick-create "+" (spec 0028), gated by `<Can>`: no abilities here, so it stays hidden.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const getInvoiceMock = vi.fn<(id: number) => Promise<InvoiceWithPermissions>>()
const patchInvoiceMock = vi.fn<(id: number, payload: Record<string, unknown>) => Promise<InvoiceWithPermissions>>()
const detailsMock = vi.fn<(id: number, payload: Record<string, unknown>) => Promise<InvoiceWithPermissions>>()

vi.mock('@/features/invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/invoices/api')>()),
  getInvoice: (id: number) => getInvoiceMock(id),
  patchInvoice: (id: number, payload: Record<string, unknown>) => patchInvoiceMock(id, payload),
  updateInvoiceDetails: (id: number, payload: Record<string, unknown>) => detailsMock(id, payload),
}))

vi.mock('@/features/invoices/invoice-document-sections', () => ({
  InvoiceDocumentSections: ({ lines }: { lines?: ReactNode }) => <div>{lines}</div>,
}))

vi.mock('@/features/outbound-emails/use-outbound-emails-tab', () => ({ useOutboundEmailsTab: () => null }))

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn().mockResolvedValue({ items: [], export_link: null, pagination: { total: 0, offset: 0, limit: 20, total_pages: 0 } }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const READONLY = { visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false }

function makeInvoice(overrides: Partial<InvoiceWithPermissions> = {}): InvoiceWithPermissions {
  return {
    id: 5,
    type: 'proforma',
    number: 3,
    year: 2026,
    number_label: '3/2026',
    document_date: '2026-10-06',
    company: { id: 3, name: 'Qualifica Srl' },
    customer: { id: 5, name: 'Acme Spa', vat_number: null },
    payment_method: { id: 2, name: 'Bonifico 30 gg' },
    financial_account: null,
    layout: null,
    proforma_request: null,
    work_order: null,
    quote: null,
    net_amount: '1000.00',
    vat_amount: '220.00',
    total_amount: '1220.00',
    collected_amount: '0.00',
    residual_amount: '1220.00',
    payment_status: 'not_due',
    external_number: null,
    external_date: null,
    notes: null,
    internal_note: null,
    tag: null,
    deviation: null,
    has_collections: false,
    lines: [
      {
        id: 1,
        quote_line_id: null,
        product: null,
        description: 'Consulting',
        quantity: '1.00',
        unit_price: '1000.00',
        vat_rate: { id: 1, name: 'IVA 22%', rate: '22.00' },
        net_amount: '1000.00',
        vat_amount: '220.00',
        total_amount: '1220.00',
        sort_order: 1,
      },
    ],
    installments: [],
    created_by: { id: 1, name: 'Mario Rossi' },
    created_at: '2026-10-06T08:00:00Z',
    updated_at: '2026-10-06T08:00:00Z',
    permissions: {
      resource: { view: true, create: false, update: true, delete: true, export: false, import: false },
      fields: {},
      actions: {},
    },
    ...overrides,
  } as InvoiceWithPermissions
}

function renderDetail(invoice: InvoiceWithPermissions) {
  getInvoiceMock.mockResolvedValue(invoice)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <InvoiceDetailView invoiceId={invoice.id} onChanged={vi.fn()} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  patchInvoiceMock.mockResolvedValue(makeInvoice())
  detailsMock.mockResolvedValue(makeInvoice())
})

describe('invoice record edited in place (spec 0261)', () => {
  it('saves a header field through the partial invoice PATCH', async () => {
    renderDetail(makeInvoice())

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Notes' }))
    fireEvent.change(screen.getByLabelText(/^Notes/), { target: { value: 'Bank transfer' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(patchInvoiceMock).toHaveBeenCalledWith(5, { notes: 'Bank transfer' }))
    expect(detailsMock).not.toHaveBeenCalled()
  })

  it('saves the internal note through the details endpoint', async () => {
    renderDetail(makeInvoice())

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Internal notes' }))
    fireEvent.change(screen.getByLabelText(/^Internal notes/), { target: { value: 'Check VAT' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(detailsMock).toHaveBeenCalledWith(5, { internal_note: 'Check VAT' }))
  })

  it('sends the external number and date together', async () => {
    renderDetail(makeInvoice())

    fireEvent.click(await screen.findByRole('button', { name: 'Edit External invoice' }))
    fireEvent.change(screen.getByLabelText(/^External invoice number/), { target: { value: '42' } })
    fireEvent.change(screen.getByLabelText(/^External invoice date/), { target: { value: '2026-10-10' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(detailsMock).toHaveBeenCalledWith(5, { external_number: '42', external_date: '2026-10-10' }),
    )
  })

  it('freezes date, customer and payment method once installments are collected', async () => {
    renderDetail(makeInvoice({ has_collections: true }))

    expect(await screen.findByRole('button', { name: 'Edit Notes' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Document date' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Customer' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Payment' })).not.toBeInTheDocument()
  })

  // Spec 0263 (REQUIREMENT CHANGED): no "Edit lines" dialog; the lines are edited in place.
  it('on a registered invoice follows the server matrix and keeps the lines read-only', async () => {
    renderDetail(
      makeInvoice({
        type: 'invoice',
        external_number: '7',
        external_date: '2026-10-08',
        permissions: {
          resource: { view: true, create: false, update: true, delete: false, export: false, import: false },
          fields: { notes: READONLY, document_date: READONLY, external_number: READONLY },
          actions: {},
        },
      }),
    )

    expect(await screen.findByRole('button', { name: 'Edit Internal notes' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Notes' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit External invoice' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit line 1' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add line' })).not.toBeInTheDocument()
  })
})

const SAVED_LINE = { quote_line_id: null, product_id: null, description: 'Consulting', quantity: 1, unit_price: 1000, vat_rate_id: 1 }

describe('invoice lines edited in place (spec 0263)', () => {
  it('opens a line in place and saves the whole lines set through the partial PATCH', async () => {
    renderDetail(makeInvoice())

    fireEvent.click(await screen.findByRole('button', { name: 'Edit line 1' }))
    fireEvent.change(screen.getByLabelText('Quantity 1'), { target: { value: '3' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(patchInvoiceMock).toHaveBeenCalledWith(5, { lines: [{ ...SAVED_LINE, quantity: 3 }] }))
  })

  it('adds a free line, opened in place with the VAT of the last line, and saves it', async () => {
    renderDetail(makeInvoice())

    fireEvent.click(await screen.findByRole('button', { name: 'Add line' }))
    fireEvent.click(screen.getByRole('button', { name: 'Free' }))
    fireEvent.change(screen.getByLabelText('Description'), { target: { value: 'Travel' } })
    fireEvent.click(screen.getByRole('button', { name: 'Free line' }))
    fireEvent.change(screen.getByLabelText('Unit price 2'), { target: { value: '50' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(patchInvoiceMock).toHaveBeenCalledWith(5, {
        lines: [SAVED_LINE, { ...SAVED_LINE, description: 'Travel', unit_price: 50 }],
      }),
    )
  })

  it('deletes a line after confirmation, keeping the others', async () => {
    const [line] = makeInvoice().lines
    renderDetail(makeInvoice({ lines: [line!, { ...line!, id: 2, description: 'Travel' }] }))

    fireEvent.click(await screen.findByRole('button', { name: 'Delete line 2' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Delete' }))

    await waitFor(() => expect(patchInvoiceMock).toHaveBeenCalledWith(5, { lines: [SAVED_LINE] }))
  })

  it('does not offer to delete the only line', async () => {
    renderDetail(makeInvoice())

    expect(await screen.findByRole('button', { name: 'Edit line 1' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Delete line 1' })).not.toBeInTheDocument()
  })
})
