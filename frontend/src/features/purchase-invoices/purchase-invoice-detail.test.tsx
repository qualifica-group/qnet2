import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { PurchaseInvoiceDetailView } from '@/features/purchase-invoices/purchase-invoice-detail'
import { makePurchaseInvoiceView } from '@/features/purchase-invoices/purchase-invoice-view-fixtures'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'

const fetchMock = vi.hoisted(() => vi.fn<(id: number) => Promise<PurchaseInvoice>>())
const canMock = vi.hoisted(() => vi.fn<(permission: string) => boolean>())

const patchMock = vi.hoisted(() => vi.fn<(id: number, payload: Record<string, unknown>) => Promise<PurchaseInvoice>>())
const detailsMock = vi.hoisted(() => vi.fn<(id: number, payload: Record<string, unknown>) => Promise<PurchaseInvoice>>())

vi.mock('@/features/purchase-invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoices/api')>()),
  fetchPurchaseInvoice: (id: number) => fetchMock(id),
  patchPurchaseInvoice: (id: number, payload: Record<string, unknown>) => patchMock(id, payload),
  updatePurchaseInvoiceDetails: (id: number, payload: Record<string, unknown>) => detailsMock(id, payload),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: ({ resource, id, canUpload }: { resource: string; id: number; canUpload: boolean }) => (
    <div role="region" aria-label="Attachments">{`${resource}/${id}/upload:${String(canUpload)}`}</div>
  ),
}))

const callbacks = { onDelete: vi.fn() }

function renderDetail(invoice: PurchaseInvoice) {
  fetchMock.mockResolvedValue(invoice)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ConfirmDialogProvider>
          <PurchaseInvoiceDetailView invoiceId={invoice.id} {...callbacks} />
        </ConfirmDialogProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  canMock.mockReturnValue(true)
  patchMock.mockResolvedValue(makePurchaseInvoiceView())
  detailsMock.mockResolvedValue(makePurchaseInvoiceView())
})

describe('PurchaseInvoiceDetailView (spec 0250 AC-013)', () => {
  it('shows the lines with cost type and work order, the installments, the linked ODAs and the documents', async () => {
    renderDetail(makePurchaseInvoiceView())

    const lines = await screen.findByRole('table', { name: 'Lines' })
    const consulting = within(lines).getByRole('row', { name: /Consulting/ })
    expect(within(consulting).getByText('Collaboratori')).toBeInTheDocument()
    expect(within(consulting).getByText('WO-9')).toBeInTheDocument()
    expect(within(lines).getByRole('row', { name: /Travel/ })).toHaveTextContent('Trasferte')

    const installments = screen.getByRole('table', { name: 'Installments' })
    expect(within(installments).getAllByRole('row')).toHaveLength(3)
    expect(within(installments).getAllByText('Unpaid')).toHaveLength(2)

    const orders = screen.getByRole('list', { name: 'Linked ODAs' })
    expect(within(orders).getByRole('link', { name: '3/2026' })).toHaveAttribute('href', '/purchase-orders/5')
    expect(within(orders).getByRole('link', { name: '4/2026' })).toBeInTheDocument()

    expect(screen.getByRole('region', { name: 'Attachments' })).toHaveTextContent('purchase_invoice/31/upload:true')
  })

  it('titles a proforma without number and a registered invoice with the supplier number', async () => {
    const { unmount } = renderDetail(makePurchaseInvoiceView())
    expect(await screen.findByRole('heading', { name: 'Passive proforma' })).toBeInTheDocument()
    unmount()

    renderDetail(makePurchaseInvoiceView({ document_type: 'invoice', document_number: 'FT-77' }))
    expect(await screen.findByRole('heading', { name: 'Invoice FT-77' })).toBeInTheDocument()
  })

  // Spec 0263 (REQUIREMENT CHANGED): no "Edit lines" button, the lines are edited in place.
  it('renders the delete button from permissions.actions and wires its callback', async () => {
    renderDetail(makePurchaseInvoiceView({ document_number: 'FT-1' }))

    fireEvent.click(await screen.findByRole('button', { name: 'Delete' }))

    expect(callbacks.onDelete).toHaveBeenCalledWith('FT-1')
  })

  it('saves a header field through the partial PATCH and the notes through the details endpoint (spec 0262)', async () => {
    renderDetail(makePurchaseInvoiceView())

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Document date' }))
    fireEvent.change(screen.getByLabelText(/^Document date/), { target: { value: '2026-10-05' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))
    await waitFor(() => expect(patchMock).toHaveBeenCalledWith(31, { document_date: '2026-10-05' }))

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Notes' }))
    fireEvent.change(screen.getByLabelText(/^Notes/), { target: { value: 'Checked' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))
    await waitFor(() => expect(detailsMock).toHaveBeenCalledWith(31, { notes: 'Checked' }))
  })

  it('freezes date and payment method once payments exist', async () => {
    renderDetail(makePurchaseInvoiceView({ has_payments: true }))

    expect(await screen.findByRole('button', { name: 'Edit Notes' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Document date' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Payment method' })).not.toBeInTheDocument()
  })

  it('hides the buttons the server does not allow (locked invoice)', async () => {
    const base = makePurchaseInvoiceView({ document_type: 'invoice', document_number: 'FT-2' })
    renderDetail({
      ...base,
      permissions: { ...base.permissions, actions: { update: false, delete: false, details: true } },
    })

    await screen.findByRole('heading', { name: 'Invoice FT-2' })
    expect(screen.queryByRole('button', { name: 'Edit line 1' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add line' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument()
  })

  it('shows plain ODA codes without the purchase-orders.view permission', async () => {
    canMock.mockImplementation((permission) => permission !== 'purchase-orders.view')
    renderDetail(makePurchaseInvoiceView())

    const orders = await screen.findByRole('list', { name: 'Linked ODAs' })
    expect(within(orders).queryByRole('link')).not.toBeInTheDocument()
    expect(within(orders).getByText('3/2026')).toBeInTheDocument()
  })
})

/** The `lines` sent by the last PATCH. */
function sentLines(): Array<Record<string, unknown>> {
  return (patchMock.mock.calls.at(-1)?.[1] as { lines: Array<Record<string, unknown>> }).lines
}

describe('passive invoice lines edited in place (spec 0263)', () => {
  it('opens a line in place and saves the whole lines set, persisted ids included', async () => {
    renderDetail(makePurchaseInvoiceView())

    fireEvent.click(await screen.findByRole('button', { name: 'Edit line 1' }))
    fireEvent.change(screen.getByLabelText('Quantity 1'), { target: { value: '3' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(patchMock).toHaveBeenCalledTimes(1))
    expect(sentLines()).toHaveLength(2)
    expect(sentLines()[0]).toMatchObject({ id: 1, purchase_order_line_id: 11, description: 'Consulting', quantity: 3 })
    expect(sentLines()[1]).toMatchObject({ id: 2, description: 'Travel' })
  })

  it('adds a free line opened in place, with the header cost type proposed, and saves it with the others', async () => {
    renderDetail(makePurchaseInvoiceView({ cost_type: { id: 4, name: 'Servizi', color: 'green' } }))

    fireEvent.click(await screen.findByRole('button', { name: 'Add line' }))
    fireEvent.click(screen.getByRole('button', { name: 'Free' }))
    fireEvent.change(screen.getByLabelText('Free row description'), { target: { value: 'Shipping' } })
    fireEvent.click(screen.getByRole('button', { name: 'Add row' }))
    fireEvent.change(screen.getByLabelText('Unit price 3'), { target: { value: '15' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(patchMock).toHaveBeenCalledTimes(1))
    expect(sentLines()).toHaveLength(3)
    expect(sentLines()[2]).toMatchObject({ description: 'Shipping', unit_price: 15, cost_type_id: 4 })
    expect(sentLines()[2]).not.toHaveProperty('id')
  })

  it('deletes a line after confirmation, keeping the other one', async () => {
    renderDetail(makePurchaseInvoiceView())

    fireEvent.click(await screen.findByRole('button', { name: 'Delete line 2' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Delete' }))

    await waitFor(() => expect(patchMock).toHaveBeenCalledTimes(1))
    expect(sentLines().map((line) => line.id)).toEqual([1])
  })
})
