import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import type { TableRow } from '@/features/table/types'
import { PurchaseRequestDetailPanel } from '@/features/purchase-requests/purchase-request-detail-panel'
import { makeLine, makeRequest } from '@/features/purchase-requests/purchase-request-fixtures'

const request = makeRequest({
  lines: [
    makeLine({ id: 31, description: 'Toner', abilities: { update: true, delete: true, transitions: ['approved', 'rejected'], capabilities: [] } }),
    makeLine({ id: 32, position: 2, description: 'Paper', status: 'ordered', abilities: { update: false, delete: false, transitions: [], capabilities: [] } }),
  ],
})

vi.mock('@/features/purchase-requests/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-requests/api')>()),
  fetchPurchaseRequest: () => Promise.resolve(request),
}))

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const params = { data: { id: request.id, actions: [] } } as unknown as ICellRendererParams<TableRow>
  render(
    <QueryClientProvider client={client}>
      <PurchaseRequestDetailPanel {...params} onChanged={vi.fn()} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('PurchaseRequestDetailPanel', () => {
  it('lists the lines of the RDA and offers the status change only where allowed', async () => {
    renderPanel()

    const toner = (await screen.findByText('Toner')).closest('tr') as HTMLElement
    const paper = screen.getByText('Paper').closest('tr') as HTMLElement
    expect(within(paper).getByText('Ordered')).toBeInTheDocument()
    expect(within(paper).queryByRole('button', { name: 'Change line status' })).not.toBeInTheDocument()

    fireEvent.click(within(toner).getByRole('button', { name: 'Change line status' }))
    expect(screen.getByRole('radio', { name: 'To approve (current)' })).toBeChecked()
  })
})
