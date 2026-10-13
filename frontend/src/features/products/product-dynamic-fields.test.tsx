import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProductDetailView } from '@/features/products/product-detail'
import { ProductForm } from '@/features/products/product-form'
import {
  RAM_ATTRIBUTE,
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
 * Spec 0061/0062: the category's PRODUCT-context attributes. REQUIREMENT
 * CHANGED (spec 0218): every attribute is a row — on create once a category
 * is picked (following the configured create layout), on the detail from the
 * persisted product, valued or not — and a category change on the detail
 * asks the new category's attributes inside its own editor (D-4).
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

const RAM_APPLICABLE = {
  id: 1,
  code: 'ram_gb',
  name: 'RAM (GB)',
  type: 'integer',
  description: null,
  help_text: null,
  placeholder: null,
  icon: null,
  config: null,
  relation_target: null,
  is_required: false,
  sort_order: 0,
  options: [],
}

const SPECIFICATIONS_LAYOUT = {
  sections: [
    {
      id: 's1',
      title: 'Specifications',
      description: null,
      variant: 'default' as const,
      collapsible: false,
      default_collapsed: false,
      columns: 1,
      sort_order: 0,
      rows: [{ id: 'r1', items: [{ attribute_code: 'ram_gb', width: 'full' as const }] }],
    },
  ],
}

describe('Product — attribute rows on create (spec 0061)', () => {
  it('shows no attribute row before a category is picked', async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    expect(screen.queryByText('RAM (GB)')).not.toBeInTheDocument()
    expect(fetchEffectiveAttributesMock).not.toHaveBeenCalled()
  })

  it("adds a row per attribute of the picked category and sends the kept value", async () => {
    fetchEffectiveAttributesMock.mockResolvedValue([RAM_ATTRIBUTE])
    createProductMock.mockResolvedValue(productFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    completeRequiredCreateRows()
    await waitFor(() => expect(fetchEffectiveAttributesMock).toHaveBeenCalledWith(3, 'product'))
    fireEvent.click(await screen.findByRole('button', { name: 'Edit RAM (GB)' }))
    fireEvent.change(screen.getByRole('spinbutton', { name: 'RAM (GB)' }), { target: { value: '16' } })
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createProductMock).toHaveBeenCalledTimes(1))
    expect(createProductMock.mock.calls[0][0].attribute_values).toEqual({ ram_gb: 16 })
  })

  it('follows the configured create layout (spec 0062 AC-014)', async () => {
    fetchEffectiveAttributesMock.mockResolvedValue([RAM_ATTRIBUTE])
    productAttributeLayoutMock.mockReturnValue({ data: SPECIFICATIONS_LAYOUT, isLoading: false })
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    completeRequiredCreateRows()

    await waitFor(() => expect(productAttributeLayoutMock).toHaveBeenCalledWith(3, 'create'))
    expect(await screen.findByText('Specifications')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit RAM (GB)' })).toBeInTheDocument()
  })
})

describe('Product — attribute rows on the detail (spec 0218)', () => {
  it('shows every attribute, valued or not, and PATCHes the edited one alone', async () => {
    fetchEffectiveAttributesMock.mockResolvedValue([RAM_ATTRIBUTE])
    updateProductMock.mockResolvedValue(productFixture({ attribute_values: { ram_gb: 32 } }))
    renderDetail(productFixture({ applicable_attributes: [RAM_APPLICABLE], attribute_values: { ram_gb: null } }))

    fireEvent.click(pencilOf('RAM (GB)'))
    fireEvent.change(screen.getByRole('spinbutton', { name: 'RAM (GB)' }), { target: { value: '32' } })
    confirmOpenRow('Save')

    await waitFor(() => expect(updateProductMock).toHaveBeenCalledWith(5, { attribute_values: { ram_gb: 32 } }))
  })

  it('offers no attribute pencil without the update permission (D-5)', () => {
    renderDetail(
      productFixture({ applicable_attributes: [RAM_APPLICABLE], permissions: permissionsFor({}, { update: false }) }),
    )

    expect(screen.getByText('RAM (GB)')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit RAM (GB)' })).not.toBeInTheDocument()
  })

  it("asks the new category's attributes inside the category editor and sends them together (D-4)", async () => {
    // The persisted category has no attribute; category 3 (the stub's pick) brings RAM.
    fetchEffectiveAttributesMock.mockImplementation((categoryId: number) =>
      Promise.resolve(categoryId === 3 ? [RAM_ATTRIBUTE] : []),
    )
    updateProductMock.mockResolvedValue(productFixture())
    renderDetail(productFixture({ category_id: 9, category: { id: 9, name: 'Desktops' }, applicable_attributes: [] }))

    fireEvent.click(pencilOf('Category'))
    fireEvent.click(screen.getByTestId('category-select'))
    expect(await screen.findByText('Attributes of the new category')).toBeInTheDocument()
    fireEvent.change(screen.getByRole('spinbutton', { name: 'RAM (GB)' }), { target: { value: '8' } })
    confirmOpenRow('Save')

    await waitFor(() =>
      expect(updateProductMock).toHaveBeenCalledWith(5, { category_id: 3, attribute_values: { ram_gb: 8 } }),
    )
  })
})
