import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductRevenueTypeDialog } from '@/features/products/product-revenue-type-dialog'
import { setProductsRevenueType } from '@/features/products/api'
import { fetchRevenueTypesForSelect } from '@/features/revenue-types/api'

vi.mock('@/features/products/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/products/api')>()),
  setProductsRevenueType: vi.fn(),
}))
vi.mock('@/features/revenue-types/api', () => ({ fetchRevenueTypesForSelect: vi.fn() }))
const toastSuccess = vi.fn()
vi.mock('sonner', () => ({ toast: { success: (m: string) => toastSuccess(m), error: vi.fn() } }))

const setMock = vi.mocked(setProductsRevenueType)
const fetchMock = vi.mocked(fetchRevenueTypesForSelect)

const TARGETS = [
  { id: 1, name: 'Fuel' },
  { id: 2, name: 'Toll' },
]

function renderDialog(onApplied = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ProductRevenueTypeDialog targets={TARGETS} onOpenChange={vi.fn()} onApplied={onApplied} />
    </QueryClientProvider>,
  )
  return onApplied
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  setMock.mockReset().mockResolvedValue({ updated: 2 })
  toastSuccess.mockReset()
  fetchMock.mockReset().mockResolvedValue({
    items: [
      {
        id: 7,
        code: '70.01.001',
        name: 'Consulting',
        path: '',
        is_active: true,
        group: { code: '70', name: 'Sales' },
        account: { code: '70.01', name: 'Services' },
      },
    ],
    total: 1,
    offset: 0,
  })
})

describe('ProductRevenueTypeDialog (spec 0350 AC-013)', () => {
  it('keeps the confirm disabled until a choice is made, then sets the sub-account on every selected product', async () => {
    const onApplied = renderDialog()
    const confirm = screen.getByRole('button', { name: 'Apply to 2 products' })
    expect(confirm).toBeDisabled()
    expect(screen.getByRole('list', { name: 'Selected products' })).toHaveTextContent('FuelToll')

    fireEvent.click(screen.getByRole('combobox', { name: 'Revenue sub-account' }))
    fireEvent.click(await screen.findByRole('option', { name: '70.01.001 Consulting' }))
    await waitFor(() => expect(confirm).toBeEnabled())
    fireEvent.click(confirm)

    await waitFor(() => expect(setMock).toHaveBeenCalledWith({ ids: [1, 2], revenue_type_id: 7 }))
    await waitFor(() => expect(toastSuccess).toHaveBeenCalledWith('Revenue sub-account updated on 2 product(s).'))
    expect(onApplied).toHaveBeenCalled()
  })

  it('"None" removes the sub-account by sending null', async () => {
    renderDialog()

    fireEvent.click(screen.getByRole('checkbox', { name: 'None (remove the sub-account)' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply to 2 products' }))

    await waitFor(() => expect(setMock).toHaveBeenCalledWith({ ids: [1, 2], revenue_type_id: null }))
  })
})
