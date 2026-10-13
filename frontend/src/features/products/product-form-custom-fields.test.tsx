import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProductDetailView } from '@/features/products/product-detail'
import { ProductForm } from '@/features/products/product-form'
import {
  EDITABLE,
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
 * Spec 0021: the universal custom fields on the product — a closed row of
 * the create draft, a row editing in place on the detail (only that field in
 * the PATCH), a 422 shown on it. REQUIREMENT CHANGED (spec 0218).
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

const WARRANTY_FIELD = {
  key: 'custom.warranty_months',
  type: 'text' as const,
  label: 'Warranty',
  group: null,
  mandatory: false,
  source: 'custom' as const,
}

beforeEach(() => {
  fetchResourceMetaMock.mockResolvedValue({
    fields: [WARRANTY_FIELD],
    permissions: permissionsFor({ 'custom.warranty_months': EDITABLE }),
  })
})

describe('Product — custom fields (spec 0021)', () => {
  it('includes the valued custom field in the create payload', async () => {
    createProductMock.mockResolvedValue(productFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    completeRequiredCreateRows()
    fireEvent.click(await screen.findByRole('button', { name: 'Edit Warranty' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Warranty' }), { target: { value: '24' } })
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createProductMock).toHaveBeenCalledTimes(1))
    expect(createProductMock.mock.calls[0][0].custom_fields).toEqual({ warranty_months: '24' })
  })

  it('shows the value on the detail and PATCHes only the changed custom field', async () => {
    updateProductMock.mockResolvedValue(productFixture({ custom_fields: { warranty_months: '36' } }))
    renderDetail(productFixture({ custom_fields: { warranty_months: '12' } }))

    expect(await screen.findByText('12')).toBeInTheDocument()
    fireEvent.click(pencilOf('Warranty'))
    fireEvent.change(screen.getByRole('textbox', { name: 'Warranty' }), { target: { value: '36' } })
    confirmOpenRow('Save')

    await waitFor(() =>
      expect(updateProductMock).toHaveBeenCalledWith(5, { custom_fields: { warranty_months: '36' } }),
    )
  })

  it('maps a 422 on custom_fields.<key> inside the open row', async () => {
    updateProductMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed',
          errors: { 'custom_fields.warranty_months': ['Warranty is invalid.'] },
        },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)
    renderDetail(productFixture({ custom_fields: { warranty_months: '12' } }))

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Warranty' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Warranty' }), { target: { value: 'x' } })
    confirmOpenRow('Save')

    expect(await screen.findByText('Warranty is invalid.')).toBeInTheDocument()
  })
})
