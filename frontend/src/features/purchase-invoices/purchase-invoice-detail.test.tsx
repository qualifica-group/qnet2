import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { PurchaseInvoiceDetailView } from '@/features/purchase-invoices/purchase-invoice-detail'
import { makePurchaseInvoiceView } from '@/features/purchase-invoices/purchase-invoice-view-fixtures'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'

const fetchMock = vi.hoisted(() => vi.fn<(id: number) => Promise<PurchaseInvoice>>())
const canMock = vi.hoisted(() => vi.fn<(permission: string) => boolean>())

vi.mock('@/features/purchase-invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoices/api')>()),
  fetchPurchaseInvoice: (id: number) => fetchMock(id),
}))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: ({ resource, id, canUpload }: { resource: string; id: number; canUpload: boolean }) => (
    <div role="region" aria-label="Attachments">{`${resource}/${id}/upload:${String(canUpload)}`}</div>
  ),
}))

const callbacks = { onEdit: vi.fn(), onDetails: vi.fn(), onDelete: vi.fn() }

function renderDetail(invoice: PurchaseInvoice) {
  fetchMock.mockResolvedValue(invoice)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <PurchaseInvoiceDetailView invoiceId={invoice.id} {...callbacks} />
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

  it('renders the header buttons from permissions.actions and wires their callbacks', async () => {
    renderDetail(makePurchaseInvoiceView({ document_number: 'FT-1' }))

    fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
    fireEvent.click(screen.getByRole('button', { name: 'Supplier details' }))
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))

    expect(callbacks.onEdit).toHaveBeenCalledTimes(1)
    expect(callbacks.onDetails).toHaveBeenCalledTimes(1)
    expect(callbacks.onDelete).toHaveBeenCalledWith('FT-1')
  })

  it('hides the buttons the server does not allow (locked invoice)', async () => {
    const base = makePurchaseInvoiceView({ document_type: 'invoice', document_number: 'FT-2' })
    renderDetail({
      ...base,
      permissions: { ...base.permissions, actions: { update: false, delete: false, details: true } },
    })

    await screen.findByRole('button', { name: 'Supplier details' })
    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
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
