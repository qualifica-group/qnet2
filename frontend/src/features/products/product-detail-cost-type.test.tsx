import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProductDetailView } from '@/features/products/product-detail'
import { pencilOf, permissionsFor, productFixture, productWrapper } from '@/features/products/product-test-fixtures'
import type { ProductDetailWithPermissions } from '@/features/products/types'

/** Spec 0251 AC-007: the "Cost type" field of the product detail. */

const updateProductMock = vi.fn()
vi.mock('@/features/products/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/products/api')>()),
  updateProduct: (...args: unknown[]) => updateProductMock(...args),
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
vi.mock('@/features/authorization/api', () => ({ fetchResourceMeta: () => fetchResourceMetaMock() }))

vi.mock('@/features/product-categories/api', () => ({ fetchEffectiveAttributes: () => Promise.resolve([]) }))
vi.mock('@/features/products/use-product-attribute-layout', () => ({
  useProductAttributeLayout: () => ({ data: null, isLoading: false }),
}))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

function renderDetail(product: ProductDetailWithPermissions) {
  const { wrapper } = productWrapper()
  render(<ProductDetailView product={product} />, { wrapper })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateProductMock.mockReset()
  fetchResourceMetaMock.mockReset().mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('ProductDetailView - cost type (spec 0251 AC-007)', () => {
  it('shows the cost type as a badge', () => {
    renderDetail(productFixture({ cost_type_id: 4, cost_type: { id: 4, name: 'Travel', color: 'blue' } }))

    expect(screen.getByText('Travel')).toBeInTheDocument()
    expect(pencilOf('Cost type')).toBeInTheDocument()
  })

  it('PATCHes the picked cost type alone', async () => {
    updateProductMock.mockResolvedValue(productFixture({ cost_type_id: 7 }))
    renderDetail(productFixture())

    fireEvent.click(pencilOf('Cost type'))
    fireEvent.click(screen.getByTestId('select-Cost type'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateProductMock).toHaveBeenCalledWith(5, { cost_type_id: 7 }))
  })
})
