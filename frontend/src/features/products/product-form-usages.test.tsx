import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProductDetailView } from '@/features/products/product-detail'
import { ProductForm } from '@/features/products/product-form'
import {
  clickCreateSave,
  completeRequiredCreateRows,
  confirmOpenRow,
  pencilOf,
  permissionsFor,
  productFixture,
  productWrapper,
} from '@/features/products/product-test-fixtures'
import type { ProductDetailWithPermissions } from '@/features/products/types'

/**
 * Spec 0142: the Offerta usages (Sellable / Usable as cost), at least one.
 * REQUIREMENT CHANGED (spec 0218): the usages are a closed row; on the detail
 * a usage switched on asks its amount in the same editor (D-4).
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

describe('Product — offer usages (spec 0142)', () => {
  it('starts a new product as Sellable only and sends that set on create', async () => {
    createProductMock.mockResolvedValue(productFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    expect(screen.getByText('Sellable')).toBeInTheDocument()
    completeRequiredCreateRows()
    clickCreateSave()

    await waitFor(() => expect(createProductMock).toHaveBeenCalledTimes(1))
    expect(createProductMock.mock.calls[0][0].usages).toEqual(['SALE'])
  })

  it('refuses the save when no usage is left, marking the row', async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    completeRequiredCreateRows()
    fireEvent.click(pencilOf('Offer usage'))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Sellable' }))
    confirmOpenRow('Done')
    clickCreateSave()

    expect(await screen.findByText('Select at least one usage.')).toBeInTheDocument()
    expect(createProductMock).not.toHaveBeenCalled()
  })

  it('asks the cost in the usages editor when the cost usage is switched on (spec 0218 D-4)', async () => {
    updateProductMock.mockResolvedValue(productFixture())
    renderDetail(productFixture({ cost: null }))

    fireEvent.click(pencilOf('Offer usage'))
    expect(screen.queryByLabelText(/^Cost/)).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('checkbox', { name: 'Usable as cost' }))
    fireEvent.change(screen.getByLabelText(/^Cost/), { target: { value: '500' } })
    confirmOpenRow('Save')

    await waitFor(() =>
      expect(updateProductMock).toHaveBeenCalledWith(5, { usages: ['SALE', 'COST'], cost: 500 }),
    )
  })
})
