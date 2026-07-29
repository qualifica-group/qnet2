import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductForm } from '@/features/products/product-form'
import type { ProductDetailWithPermissions } from '@/features/products/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'

/**
 * Regression: `cost`/`price` are `decimal:2` casts, so the API returns them
 * as the STRINGS `"800.00"`/`"1200.00"`. Seeding those strings straight into
 * the form made its zod schema reject them ("expected number, received
 * string") as soon as an edit form was reopened on a saved product, and made
 * the sparse PATCH treat every untouched money field as changed.
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

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ value }: { value: number | null }) => (
    <div data-testid="product-select-value">{value ?? ''}</div>
  ),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/products/use-product-attribute-layout', () => ({
  useProductAttributeLayout: () => ({ data: null, isLoading: false }),
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

/** A product as the API actually serializes it: money fields are decimal strings. */
function product(overrides: Partial<ProductDetailWithPermissions> = {}): ProductDetailWithPermissions {
  return {
    id: 5,
    code: 'PRD-0005',
    name: 'ThinkPad X1',
    description: null,
    cost: '800.00',
    price: '1200.00',
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
})

describe('ProductFormBody — decimal cost/price hydration', () => {
  it('seeds the money inputs as numbers from the decimal strings the API returns', async () => {
    render(
      <ProductForm mode={{ type: 'edit', product: product() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByLabelText(/^Cost/)).toHaveValue(800)
    expect(screen.getByLabelText(/^Price/)).toHaveValue(1200)
  })

  it('submits an edit without a type error on cost/price, PATCHing only what changed', async () => {
    updateProductMock.mockResolvedValue(product())

    render(
      <ProductForm mode={{ type: 'edit', product: product() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'ThinkPad X1 Gen 2' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateProductMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductMock.mock.calls[0]
    expect(payload).toEqual({ name: 'ThinkPad X1 Gen 2' })
    expect(screen.queryByText(/expected number/i)).not.toBeInTheDocument()
  })
})
