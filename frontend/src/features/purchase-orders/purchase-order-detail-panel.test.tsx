import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import type { TableRow } from '@/features/table/types'
import { PurchaseOrderDetailPanel } from '@/features/purchase-orders/purchase-order-detail-panel'
import { makeOrderLine, makePurchaseOrder } from '@/features/purchase-orders/purchase-order-fixtures'

const order = makePurchaseOrder({
  lines: [
    makeOrderLine({ id: 31, description: 'Toner', cost_type: { id: 4, name: 'Altro' } }),
    makeOrderLine({ id: 32, position: 2, description: 'Rent', cost_type: { id: 2, name: 'Fitti' }, unit_of_measure: null }),
  ],
})

vi.mock('@/features/purchase-orders/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-orders/api')>()),
  fetchPurchaseOrder: () => Promise.resolve(order),
}))

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const params = { data: { id: order.id, actions: [] } } as unknown as ICellRendererParams<TableRow>
  render(
    <QueryClientProvider client={client}>
      <PurchaseOrderDetailPanel {...params} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('PurchaseOrderDetailPanel', () => {
  it('lists the lines of the order with their cost type', async () => {
    renderPanel()

    const toner = (await screen.findByText('Toner')).closest('tr') as HTMLElement
    const rent = screen.getByText('Rent').closest('tr') as HTMLElement
    expect(within(toner).getByText('Altro')).toBeInTheDocument()
    expect(within(rent).getByText('Fitti')).toBeInTheDocument()
    expect(within(rent).getByText('—')).toBeInTheDocument()
    expect(screen.getByRole('table', { name: 'Lines' })).toBeInTheDocument()
  })
})
