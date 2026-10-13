import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProductDetailView } from '@/features/products/product-detail'
import { ProductForm } from '@/features/products/product-form'
import {
  clickCreateSave,
  confirmOpenRow,
  inputOf,
  pencilOf,
  permissionsFor,
  productFixture,
  productWrapper,
  typeInRow,
} from '@/features/products/product-test-fixtures'
import type { ProductDetailWithPermissions } from '@/features/products/types'

/**
 * Spec 0191: the usages decide which amount applies — the price for a
 * Sellable product, the cost for a Usable-as-cost one, the margin only with
 * both. REQUIREMENT CHANGED (spec 0218): the amounts are closed rows that
 * follow the draft's usages, and the margin is the live KPI strip in the
 * create header (no side recap any more).
 */

const createProductMock = vi.fn()
const updateProductMock = vi.fn()
const fetchProductNextCodeMock = vi.fn<() => Promise<string>>()

vi.mock('@/features/products/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/products/api')>()),
  createProduct: (...args: unknown[]) => createProductMock(...args),
  updateProduct: (...args: unknown[]) => updateProductMock(...args),
  fetchProductNextCode: () => fetchProductNextCodeMock(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/config/use-config', async () => {
  const { PRODUCT_ENUMS } = await import('@/features/products/product-test-fixtures')
  return { useEnumOptions: (key: string) => PRODUCT_ENUMS[key] ?? [] }
})

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: [], isPending: false, isError: false, refetch: () => {} }),
}))

vi.mock('@/components/ui/searchable-select', async () => ({
  SearchableSelect: (await import('@/features/products/product-test-stubs')).StubSearchableSelect,
}))

vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/products/product-test-stubs')).StubAsyncSelect,
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const fetchEffectiveAttributesMock = vi.fn()
vi.mock('@/features/product-categories/api', () => ({
  fetchEffectiveAttributes: (...args: unknown[]) => fetchEffectiveAttributesMock(...args),
}))

const productAttributeLayoutMock = vi.fn()
vi.mock('@/features/products/use-product-attribute-layout', () => ({
  useProductAttributeLayout: (...args: unknown[]) => productAttributeLayoutMock(...args),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

function renderCreate(onSuccess = vi.fn()) {
  const { client, wrapper } = productWrapper()
  render(<ProductForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper })
  return client
}

function renderDetail(product: ProductDetailWithPermissions) {
  const { client, wrapper } = productWrapper()
  render(<ProductDetailView product={product} />, { wrapper })
  return client
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.restoreAllMocks()
  createProductMock.mockReset()
  updateProductMock.mockReset()
  fetchProductNextCodeMock.mockReset()
  fetchProductNextCodeMock.mockResolvedValue('PRD-0100')
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
  fetchEffectiveAttributesMock.mockReset()
  fetchEffectiveAttributesMock.mockResolvedValue([])
  productAttributeLayoutMock.mockReset()
  productAttributeLayoutMock.mockReturnValue({ data: null, isLoading: false })
})

/** Opens the usages row and toggles the usage checkbox named `label`, keeping it with Done. */
function toggleUsage(label: string) {
  fireEvent.click(pencilOf('Offer usage'))
  fireEvent.click(screen.getByRole('checkbox', { name: label }))
  confirmOpenRow('Done')
}

describe('Product — cost/price by usage (spec 0191)', () => {
  it('AC-009: shows only the price row by default, adds the cost with the cost usage, drops the price without Sellable', async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    expect(pencilOf('Price')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Cost' })).not.toBeInTheDocument()

    toggleUsage('Usable as cost')
    expect(pencilOf('Cost')).toBeInTheDocument()

    toggleUsage('Sellable')
    expect(screen.queryByRole('button', { name: 'Edit Price' })).not.toBeInTheDocument()
  })

  it('AC-009: a cost-only product saves without a price, sending it as null', async () => {
    createProductMock.mockResolvedValue(productFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    typeInRow('Name', 'Service fee')
    toggleUsage('Usable as cost')
    toggleUsage('Sellable')
    typeInRow('Cost', '40')
    fireEvent.click(pencilOf('Category'))
    fireEvent.click(screen.getByTestId('category-select'))
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createProductMock).toHaveBeenCalledTimes(1))
    expect(createProductMock.mock.calls[0][0]).toMatchObject({ usages: ['COST'], cost: 40, price: null })
  })

  it('AC-010: a typed price is still there after Sellable is unticked and ticked again', async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    typeInRow('Price', '99')
    toggleUsage('Usable as cost')
    toggleUsage('Sellable')
    toggleUsage('Sellable')

    fireEvent.click(pencilOf('Price'))
    expect(inputOf('Price')).toHaveValue(99)
  })

  it('AC-010: dropping a usage on the detail does not put cost/price in the PATCH', async () => {
    updateProductMock.mockResolvedValue(productFixture())
    renderDetail(productFixture({ usages: ['SALE', 'COST'] }))

    fireEvent.click(pencilOf('Offer usage'))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Usable as cost' }))
    confirmOpenRow('Save')

    await waitFor(() => expect(updateProductMock).toHaveBeenCalledWith(5, { usages: ['SALE'] }))
  })

  it('AC-012: the live margin in the create header follows the usages', async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    expect(screen.queryByText('Margin')).not.toBeInTheDocument()
    toggleUsage('Usable as cost')
    typeInRow('Price', '100')
    typeInRow('Cost', '40')

    expect(screen.getByText('Margin')).toBeInTheDocument()
    expect(screen.getByText('60.00')).toBeInTheDocument()
  })
})
