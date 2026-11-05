import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { GenerateOrdersDialog } from '@/features/purchase-requests/generate-orders-dialog'
import type { LineTarget } from '@/features/purchase-requests/line-status-transitions'
import type { GenerateOrdersPayload, GenerateOrdersResult } from '@/features/purchase-requests/types'

// The pickers render the quick-create "+" (spec 0028), gated by `<Can>`: no abilities here, so it stays hidden.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const generateMock = vi.fn<(payload: GenerateOrdersPayload) => Promise<GenerateOrdersResult>>()
vi.mock('@/features/purchase-requests/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-requests/api')>()),
  generatePurchaseOrders: (payload: GenerateOrdersPayload) => generateMock(payload),
}))

/** Spec 0350 D-12 contract: placed sub-accounts carry code/group/account, the unplaced ones null. */
const COST_SUB_ACCOUNTS = [
  { id: 3, label: 'Services', meta: { color: 'blue', nature: 'direct' }, code: '61.01.001', path: '61 Costs > 61.01 Purchases > 61.01.001 Services', group: { code: '61', name: 'Costs' }, account: { code: '61.01', name: 'Purchases' } },
  { id: 5, label: 'Misc', meta: { color: null, nature: 'general' }, code: null, path: null, group: null, account: null },
]

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn(async (resource: string) => ({
    items: resource === 'cost-types' ? COST_SUB_ACCOUNTS : [{ id: 4, label: 'Bank transfer' }],
    export_link: null,
    pagination: { total: 1, offset: 0, limit: 25, total_pages: 1 },
  })),
}))

function target(overrides: Partial<LineTarget> & Pick<LineTarget, 'id'>): LineTarget {
  return {
    status: 'approved',
    transitions: [],
    capabilities: ['fulfill'],
    description: `Line ${overrides.id}`,
    quantity: 1,
    unitSymbol: 'pc',
    totalAmount: 10,
    supplier: { id: 12, name: 'Rossi Srl' },
    canUpdateSupplier: true,
    purchaseOrder: null,
    purchaseRequestId: 5,
    purchaseRequestSubject: 'Laptops',
    ...overrides,
  }
}

const TARGETS = [target({ id: 1 }), target({ id: 2 }), target({ id: 3, supplier: { id: 13, name: 'Bianchi Spa' } })]

function renderDialog(onGenerated = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <GenerateOrdersDialog targets={TARGETS} onOpenChange={vi.fn()} onGenerated={onGenerated} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
  return onGenerated
}

async function pickCostType() {
  fireEvent.click(screen.getByRole('combobox', { name: 'Cost sub-account' }))
  fireEvent.click(await screen.findByRole('option', { name: '61.01.001 Services' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  generateMock.mockReset()
})

describe('GenerateOrdersDialog - cost sub-account picker (spec 0350 D-12 AC-017)', () => {
  it('groups by group and account, then a "To be placed" group, and shows code + name', async () => {
    renderDialog()

    fireEvent.click(screen.getByRole('combobox', { name: 'Cost sub-account' }))
    await screen.findByRole('option', { name: '61.01.001 Services' })

    const group = screen.getByRole('group', { name: '61 Costs' })
    expect(within(group).getByRole('group', { name: '61.01 Purchases' })).toBeInTheDocument()
    const unplaced = screen.getByRole('group', { name: 'To be placed' })
    expect(within(unplaced).getByRole('option', { name: 'Misc' })).toBeInTheDocument()
    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['61.01.001 Services', 'Misc'])
  })

  it('picks a placed sub-account and shows code + name in the trigger', async () => {
    renderDialog()

    await pickCostType()

    expect(screen.getByRole('combobox', { name: 'Cost sub-account' })).toHaveTextContent('61.01.001 Services')
  })

  it('picks an unplaced sub-account (name only)', async () => {
    renderDialog()

    fireEvent.click(screen.getByRole('combobox', { name: 'Cost sub-account' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Misc' }))

    expect(screen.getByRole('combobox', { name: 'Cost sub-account' })).toHaveTextContent('Misc')
  })
})

describe('GenerateOrdersDialog (spec 0210 AC-022)', () => {
  it('previews the ODA per supplier and requires the cost type before confirming', async () => {
    renderDialog()

    expect(screen.getByText('2 ODA will be created (one per supplier).')).toBeInTheDocument()
    const groups = screen.getByRole('list', { name: 'Selected lines by supplier' })
    expect(groups).toHaveTextContent('Rossi Srl')
    expect(groups).toHaveTextContent('2 lines')
    expect(groups).toHaveTextContent('Bianchi Spa')
    const confirm = screen.getByRole('button', { name: 'Generate 2 ODA' })
    expect(confirm).toBeDisabled()

    await pickCostType()
    await waitFor(() => expect(confirm).toBeEnabled())
  })

  it('posts the payload and shows a link to each created ODA', async () => {
    generateMock.mockResolvedValue({
      purchase_orders: [
        { id: 70, code: 'ODA-70', supplier: { id: 12, name: 'Rossi Srl' }, lines_count: 2 },
        { id: 71, code: 'ODA-71', supplier: { id: 13, name: 'Bianchi Spa' }, lines_count: 1 },
      ],
      closed_purchase_request_ids: [5],
    })
    const onGenerated = renderDialog()

    await pickCostType()
    fireEvent.click(screen.getByRole('button', { name: 'Generate 2 ODA' }))

    await waitFor(() => expect(generateMock).toHaveBeenCalledTimes(1))
    expect(generateMock.mock.calls[0][0]).toMatchObject({ line_ids: [1, 2, 3], cost_type_id: 3 })
    expect(generateMock.mock.calls[0][0]).not.toHaveProperty('payment_method_id')
    expect(await screen.findByRole('link', { name: 'ODA-70' })).toHaveAttribute('href', '/purchase-orders/70')
    expect(screen.getByRole('link', { name: 'ODA-71' })).toHaveAttribute('href', '/purchase-orders/71')
    expect(screen.getByText('1 RDA closed automatically.')).toBeInTheDocument()
    expect(onGenerated).toHaveBeenCalledTimes(1)
  })

  it('shows the 422 line messages readably and stays on the form', async () => {
    const response = {
      status: 422,
      data: { message: 'Invalid', errors: { 'line_ids.1': ['Line 2 has no supplier.'] } },
    } as AxiosResponse
    generateMock.mockRejectedValue(new AxiosError('x', '422', undefined, undefined, response))
    renderDialog()

    await pickCostType()
    fireEvent.click(screen.getByRole('button', { name: 'Generate 2 ODA' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Unable to generate the ODA. No line was changed.')
    expect(alert).toHaveTextContent('Line 2 has no supplier.')
  })
})
