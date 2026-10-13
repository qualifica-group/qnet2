import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProductDetailView } from '@/features/products/product-detail'
import { ProductForm } from '@/features/products/product-form'
import {
  clickCreateSave,
  completeRequiredCreateRows,
  inputOf,
  pencilOf,
  permissionsFor,
  productFixture,
  productWrapper,
  typeInRow,
} from '@/features/products/product-test-fixtures'
import type { ProductDetailWithPermissions } from '@/features/products/types'

/**
 * Spec 0065 AC-079/AC-080/AC-081: the manual code — the create row starts
 * with the next sequential suggestion (AC-079); on the detail the code is
 * read-only and never sent (AC-080); a 422 duplicate shows on its row
 * (AC-081). REQUIREMENT CHANGED (spec 0218): rows are closed until opened,
 * and the edit cases run on the in-place detail.
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

describe('Product — manual code (spec 0065 AC-079/AC-080)', () => {
  it('starts the create code row with the next sequential suggestion (AC-079)', async () => {
    renderCreate()

    expect((await screen.findAllByText('PRD-0100')).length).toBeGreaterThan(0)
    fireEvent.click(pencilOf('Code'))
    expect(inputOf('Code')).toHaveValue('PRD-0100')
  })

  it('sends the trimmed manual code on create when the user overrides it (AC-079)', async () => {
    createProductMock.mockResolvedValue(productFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    typeInRow('Code', '  CUSTOM-1  ')
    completeRequiredCreateRows()
    clickCreateSave()

    await waitFor(() => expect(createProductMock).toHaveBeenCalledTimes(1))
    expect(createProductMock.mock.calls[0][0].code).toBe('CUSTOM-1')
  })

  it('shows the saved code without a pencil on the detail, never sending it (AC-080)', async () => {
    updateProductMock.mockResolvedValue(productFixture({ name: 'ThinkPad X2' }))
    renderDetail(productFixture())

    expect(screen.getAllByText('PRD-0005').length).toBeGreaterThan(0)
    expect(screen.queryByRole('button', { name: 'Edit Code' })).not.toBeInTheDocument()
    expect(fetchProductNextCodeMock).not.toHaveBeenCalled()

    typeInRow('Name', 'ThinkPad X2', 'Save')

    await waitFor(() => expect(updateProductMock).toHaveBeenCalledWith(5, { name: 'ThinkPad X2' }))
  })
})

describe('Product — 422 duplicate code (spec 0065 AC-081)', () => {
  it('maps a 422 on the code onto its row, not only a toast', async () => {
    createProductMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { code: ['This code is already used.'] } },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    completeRequiredCreateRows()
    clickCreateSave()

    expect(await screen.findByText('This code is already used.')).toBeInTheDocument()
  })
})
