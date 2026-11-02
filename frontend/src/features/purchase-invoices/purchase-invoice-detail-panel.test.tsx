import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { PurchaseInvoiceDetailPanel } from '@/features/purchase-invoices/purchase-invoice-detail-panel'
import { makePurchaseInvoiceView } from '@/features/purchase-invoices/purchase-invoice-view-fixtures'
import { purchaseDetail } from '@/features/purchase-invoice-installments/purchase-installment-fixtures'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'
import type { TableRow } from '@/features/table/types'

let invoice: PurchaseInvoice = makePurchaseInvoiceView()
const installmentMock = vi.hoisted(() => vi.fn<(id: number) => Promise<unknown>>())

vi.mock('@/features/purchase-invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoices/api')>()),
  fetchPurchaseInvoice: () => Promise.resolve(invoice),
}))
vi.mock('@/features/purchase-invoice-installments/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoice-installments/api')>()),
  getPurchaseInstallment: (id: number) => installmentMock(id),
}))
// The shared async select is tested on its own: the payment dialog only has to open here.
vi.mock('@/features/purchase-invoice-installments/purchase-financial-account-select', () => ({
  PurchaseFinancialAccountSelect: (props: { label: string }) => <button type="button" aria-label={props.label} />,
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
  invoice = makePurchaseInvoiceView()
  canMock.mockReset()
  canMock.mockReturnValue(false)
  installmentMock.mockReset()
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

  it('offers "Record payment" on every unpaid installment and opens the payment dialog on the one fetched fresh', async () => {
    installmentMock.mockResolvedValue(purchaseDetail({ id: 71, sequence: 1, due_date: '2026-10-31', amount: '183.00' }))
    renderPanel()

    const installments = await screen.findByRole('table', { name: 'Installments' })
    const buttons = within(installments).getAllByRole('button', { name: 'Record payment' })
    expect(buttons).toHaveLength(2)

    fireEvent.click(buttons[0]!)

    expect(await screen.findByRole('dialog', { name: 'Record payment' })).toBeInTheDocument()
    expect(installmentMock).toHaveBeenCalledWith(71)
  })

  it('offers "Clear payment" on a paid installment', async () => {
    invoice = makePurchaseInvoiceView({
      installments: [
        { id: 71, sequence: 1, due_date: '2026-10-31', amount: '183.00', payment_method_code: 'MP05', status: 'paid', paid_amount: '183.00', paid_at: '2026-10-20' },
        { id: 72, sequence: 2, due_date: '2026-11-30', amount: '183.00', payment_method_code: 'MP05', status: 'unpaid' },
      ],
    })
    renderPanel()

    const installments = await screen.findByRole('table', { name: 'Installments' })
    expect(within(installments).getByRole('button', { name: 'Clear payment' })).toBeInTheDocument()
    expect(within(installments).getByRole('button', { name: 'Record payment' })).toBeInTheDocument()
  })

  it('hides the payment actions without the pay ability', async () => {
    invoice = makePurchaseInvoiceView({
      permissions: { ...invoice.permissions, actions: { ...invoice.permissions.actions, pay: false } },
    })
    renderPanel()

    const installments = await screen.findByRole('table', { name: 'Installments' })
    expect(within(installments).queryByRole('button')).not.toBeInTheDocument()
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
