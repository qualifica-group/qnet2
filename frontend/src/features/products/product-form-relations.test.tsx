import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductForm } from '@/features/products/product-form'
import type { ProductDetailWithPermissions } from '@/features/products/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'

/**
 * The VAT rate + Supplier + Region relation pickers added to the product
 * form: all render, the supplier picker scopes its `registries` for-select
 * request to `is_supplier`, the region picker requests the `states`
 * for-select resource, and edit mode hydrates from the loaded product's
 * `vat_rate`/`supplier`/`state` projections.
 */

const createProductMock = vi.fn()
const updateProductMock = vi.fn()

vi.mock('@/features/products/api', () => ({
  createProduct: (...args: unknown[]) => createProductMock(...args),
  updateProduct: (...args: unknown[]) => updateProductMock(...args),
  fetchProductNextCode: () => Promise.resolve('PRD-0100'),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

vi.mock('@/features/config/use-config', () => ({
  useEnumOptions: () => [
    { value: 'SERVICE', label: 'Service', color: null, icon: null, is_default: true, hidden_on_form: false },
  ],
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

// Spec 0062: this suite is not about the attribute layout, so no layout is
// ever configured — keeps edit mode's immediate category id deterministic
// (no real network round-trip) without touching the relation-picker assertions below.
vi.mock('@/features/products/use-product-attribute-layout', () => ({
  useProductAttributeLayout: () => ({ data: null, isLoading: false }),
}))

const useForSelectMock = vi.fn()
vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<
    typeof import('@/features/for-select/use-for-select')
  >('@/features/for-select/use-for-select')
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: (args: unknown) => useForSelectMock(args),
    useForSelectLabels: () => new Map(),
  }
})

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

const FULL_ACCESS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function product(overrides: Partial<ProductDetailWithPermissions> = {}): ProductDetailWithPermissions {
  return {
    id: 5,
    code: 'PRD-0005',
    name: 'ThinkPad X1',
    description: null,
    cost: 800,
    price: 1200,
    category_id: 3,
    category: { id: 3, name: 'Laptops' },
    product_type: 'SERVICE',
    created_at: '2026-01-01T00:00:00Z',
    vat_rate_id: null,
    vat_rate: null,
    supplier_id: null,
    supplier: null,
    state_id: null,
    state: null,
    permissions: FULL_ACCESS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createProductMock.mockReset()
  updateProductMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_ACCESS })
  canMock.mockReset()
  canMock.mockReturnValue(false)
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

describe('ProductFormBody — VAT rate + Supplier relation fields', () => {
  it('renders both pickers in create mode', async () => {
    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(await screen.findByRole('combobox', { name: 'VAT' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Supplier' })).toBeInTheDocument()
  })

  it('requests the vat-rates resource when the VAT picker opens', async () => {
    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(await screen.findByRole('combobox', { name: 'VAT' }))

    await waitFor(() =>
      expect(useForSelectMock).toHaveBeenCalledWith(
        expect.objectContaining({ resource: 'vat-rates' }),
      ),
    )
  })

  it('scopes the supplier picker to registries with the is_supplier param', async () => {
    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(await screen.findByRole('combobox', { name: 'Supplier' }))

    await waitFor(() =>
      expect(useForSelectMock).toHaveBeenCalledWith(
        expect.objectContaining({ resource: 'registries', params: { is_supplier: 1 } }),
      ),
    )
  })

  it('hydrates the VAT rate and supplier trigger labels in edit mode', async () => {
    render(
      <ProductForm
        mode={{
          type: 'edit',
          product: product({
            vat_rate_id: 4,
            vat_rate: { id: 4, name: 'Standard 22%', rate: 22 },
            supplier_id: 11,
            supplier: { id: 11, name: 'ACME Supplies' },
          }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('combobox', { name: 'VAT' })).toHaveTextContent('Standard 22%')
    expect(screen.getByRole('combobox', { name: 'Supplier' })).toHaveTextContent('ACME Supplies')
  })

  it('renders the region picker in create mode', async () => {
    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(await screen.findByRole('combobox', { name: 'Region' })).toBeInTheDocument()
  })

  it('requests the states resource when the region picker opens', async () => {
    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(await screen.findByRole('combobox', { name: 'Region' }))

    await waitFor(() =>
      expect(useForSelectMock).toHaveBeenCalledWith(expect.objectContaining({ resource: 'states' })),
    )
  })

  it('hydrates the region trigger label in edit mode', async () => {
    render(
      <ProductForm
        mode={{
          type: 'edit',
          product: product({
            state_id: 7,
            state: { id: 7, name: 'Lombardia' },
          }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('combobox', { name: 'Region' })).toHaveTextContent('Lombardia')
  })
})
