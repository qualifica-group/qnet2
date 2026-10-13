import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProductDetailView } from '@/features/products/product-detail'
import {
  READ_ONLY,
  RAM_ATTRIBUTE,
  inputOf,
  pencilOf,
  permissionsFor,
  productFixture,
  productWrapper,
  typeInRow,
} from '@/features/products/product-test-fixtures'
import type { ProductDetailWithPermissions } from '@/features/products/types'

/**
 * Spec 0218: the product detail edits in place, one row at a time. Each save
 * is a PATCH of that field alone.
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

describe('ProductDetailView — in-place editing (spec 0218)', () => {
  it('PATCHes the edited field alone and seeds the cache with the permissions', async () => {
    const saved = productFixture({ name: 'ThinkPad X2' })
    updateProductMock.mockResolvedValue(saved)
    const client = renderDetail(productFixture())

    typeInRow('Name', 'ThinkPad X2', 'Save')

    await waitFor(() => expect(updateProductMock).toHaveBeenCalledWith(5, { name: 'ThinkPad X2' }))
    expect(client.getQueryData<ProductDetailWithPermissions>(['products', 'detail', 5])?.permissions).toEqual(
      saved.permissions,
    )
  })

  it('closes on Cancel without calling the server', () => {
    renderDetail(productFixture())

    fireEvent.click(pencilOf('Name'))
    fireEvent.change(inputOf('Name'), { target: { value: 'Changed' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.getAllByText('ThinkPad X1').length).toBeGreaterThan(0)
    expect(updateProductMock).not.toHaveBeenCalled()
  })

  it('offers no pencil on a field the actor cannot edit', () => {
    renderDetail(productFixture({ permissions: permissionsFor({ code: READ_ONLY, name: READ_ONLY }) }))

    expect(screen.queryByRole('button', { name: 'Edit Name' })).not.toBeInTheDocument()
    expect(pencilOf('Description')).toBeInTheDocument()
  })

  it('shows a 422 on the edited field inside its editor', async () => {
    updateProductMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)
    renderDetail(productFixture())

    typeInRow('Name', 'Dup', 'Save')

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('a cancelled category change brings the attribute set back (spec 0218 D-4)', async () => {
    fetchEffectiveAttributesMock.mockImplementation((categoryId: number) =>
      Promise.resolve(categoryId === 3 ? [{ ...RAM_ATTRIBUTE, is_required: true }] : []),
    )
    updateProductMock.mockResolvedValue(productFixture())
    renderDetail(productFixture({ category_id: 9, category: { id: 9, name: 'Desktops' }, applicable_attributes: [] }))

    fireEvent.click(pencilOf('Category'))
    fireEvent.click(screen.getByTestId('category-select'))
    await screen.findByText('Attributes of the new category')
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    // The abandoned category's required attribute must not refuse an unrelated save.
    typeInRow('Name', 'ThinkPad X2', 'Save')
    await waitFor(() => expect(updateProductMock).toHaveBeenCalledWith(5, { name: 'ThinkPad X2' }))
  })
})
