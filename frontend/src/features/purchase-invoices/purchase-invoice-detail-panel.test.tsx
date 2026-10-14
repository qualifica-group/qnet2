import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { PurchaseInvoiceDetailPanel } from '@/features/purchase-invoices/purchase-invoice-detail-panel'
import { makePurchaseInvoiceView } from '@/features/purchase-invoices/purchase-invoice-view-fixtures'
import type { TableRow } from '@/features/table/types'

const invoice = makePurchaseInvoiceView()

vi.mock('@/features/purchase-invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoices/api')>()),
  fetchPurchaseInvoice: () => Promise.resolve(invoice),
}))

const canMock = vi.hoisted(() => vi.fn<(permission: string) => boolean>())
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const params = { data: { id: invoice.id, actions: [] } } as unknown as ICellRendererParams<TableRow>
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <PurchaseInvoiceDetailPanel {...params} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(false)
})

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('PurchaseInvoiceDetailPanel', () => {
  it('shows the lines and the installments of the expanded document', async () => {
    renderPanel()

    const lines = await screen.findByRole('table', { name: 'Lines' })
    expect(within(lines).getByText('Consulting')).toBeInTheDocument()
    expect(within(lines).getByText('Travel')).toBeInTheDocument()

    const installments = screen.getByRole('table', { name: 'Installments' })
    expect(within(installments).getAllByRole('row')).toHaveLength(invoice.installments.length + 1)
  })

  it('shows the linked ODAs as links when the actor can view purchase orders', async () => {
    canMock.mockImplementation((permission) => permission === 'purchase-orders.view')
    renderPanel()

    const orders = await screen.findByRole('list', { name: 'Linked ODAs' })
    expect(within(orders).getByRole('link', { name: '3/2026' })).toHaveAttribute('href', '/purchase-orders/5')
    expect(within(orders).getByRole('link', { name: '4/2026' })).toBeInTheDocument()
  })

  it('shows the linked ODA codes as plain text without the permission', async () => {
    renderPanel()

    const orders = await screen.findByRole('list', { name: 'Linked ODAs' })
    expect(within(orders).getByText('3/2026')).toBeInTheDocument()
    expect(within(orders).queryByRole('link')).not.toBeInTheDocument()
  })
})
