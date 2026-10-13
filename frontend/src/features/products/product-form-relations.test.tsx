import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProductDetailView } from '@/features/products/product-detail'
import { ProductForm } from '@/features/products/product-form'
import {
  PRODUCT_ENUMS,
  pencilOf,
  permissionsFor,
  productFixture,
  productWrapper,
} from '@/features/products/product-test-fixtures'

/**
 * The VAT rate, supplier and unit of measure pickers: each queries its own
 * for-select resource, the supplier scoped to `is_supplier` registries, and
 * the detail rows name the assigned records. REQUIREMENT CHANGED (spec 0218):
 * the pickers live in closed rows, opened by their pencil.
 */

vi.mock('@/features/products/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/products/api')>()),
  createProduct: vi.fn(),
  updateProduct: vi.fn(),
  fetchProductNextCode: () => Promise.resolve('PRD-0100'),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/config/use-config', () => ({
  useEnumOptions: (key: string) => PRODUCT_ENUMS[key] ?? [],
}))

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: [], isPending: false, isError: false, refetch: () => {} }),
}))

vi.mock('@/components/ui/searchable-select', () => ({
  SearchableSelect: () => <div data-testid="category-select-stub" />,
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/product-categories/api', () => ({
  fetchEffectiveAttributes: () => Promise.resolve([]),
}))

vi.mock('@/features/products/use-product-attribute-layout', () => ({
  useProductAttributeLayout: () => ({ data: null, isLoading: false }),
}))

const useForSelectMock = vi.fn()
vi.mock('@/features/for-select/use-for-select', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/features/for-select/use-for-select')>()
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: (args: unknown) => useForSelectMock(args),
    useForSelectLabels: () => new Map(),
  }
})

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

function renderCreate() {
  const { wrapper } = productWrapper()
  render(<ProductForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
  useForSelectMock.mockReset()
  useForSelectMock.mockReturnValue({
    data: { pages: [{ items: [] }] },
    isPending: false,
    isError: false,
    fetchNextPage: vi.fn(),
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
  })
})

describe('Product — VAT rate, supplier and unit of measure', () => {
  it('requests the vat-rates resource when the VAT picker opens', async () => {
    renderCreate()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit VAT' }))
    fireEvent.click(screen.getByRole('combobox', { name: 'VAT' }))

    await waitFor(() => expect(useForSelectMock).toHaveBeenCalledWith(expect.objectContaining({ resource: 'vat-rates' })))
  })

  it('scopes the supplier picker to registries with the is_supplier param', async () => {
    renderCreate()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Supplier' }))
    fireEvent.click(screen.getByRole('combobox', { name: 'Supplier' }))

    await waitFor(() =>
      expect(useForSelectMock).toHaveBeenCalledWith(
        expect.objectContaining({ resource: 'registries', params: { is_supplier: 1 } }),
      ),
    )
  })

  it('requests the units-of-measure resource when the unit of measure picker opens', async () => {
    renderCreate()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Unit of measure' }))
    fireEvent.click(screen.getByRole('combobox', { name: 'Unit of measure' }))

    await waitFor(() =>
      expect(useForSelectMock).toHaveBeenCalledWith(expect.objectContaining({ resource: 'units-of-measure' })),
    )
  })

  it('names the assigned records on the detail rows and hydrates their pickers', () => {
    const { wrapper } = productWrapper()
    render(
      <ProductDetailView
        product={productFixture({
          vat_rate_id: 4,
          vat_rate: { id: 4, name: 'Standard 22%', rate: 22 },
          supplier_id: 11,
          supplier: { id: 11, name: 'ACME Supplies' },
          unit_of_measure_id: 2,
          unit_of_measure: { id: 2, name: 'Kilogram', symbol: 'kg' },
        })}
      />,
      { wrapper },
    )

    expect(screen.getByText('Standard 22%')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /ACME Supplies/ })).toBeInTheDocument()
    expect(screen.getByText('Kilogram')).toBeInTheDocument()

    fireEvent.click(pencilOf('VAT'))
    expect(screen.getByRole('combobox', { name: 'VAT' })).toHaveTextContent('Standard 22%')
  })
})
