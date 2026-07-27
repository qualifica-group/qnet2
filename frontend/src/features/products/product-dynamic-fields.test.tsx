import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductForm } from '@/features/products/product-form'
import type { ProductDetailWithPermissions } from '@/features/products/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/**
 * Spec 0061: the product form's dynamic-fields block, driven by the selected
 * category's PRODUCT-context effective attributes. Reuses the same isolation
 * harness as `product-form-custom-fields.test.tsx` (stubbed category/VAT/
 * supplier pickers), mocking `fetchEffectiveAttributes` instead of the
 * custom-fields meta.
 */

const createProductMock = vi.fn()
const updateProductMock = vi.fn()

vi.mock('@/features/products/api', () => ({
  createProduct: (...args: unknown[]) => createProductMock(...args),
  updateProduct: (...args: unknown[]) => updateProductMock(...args),
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
  SearchableSelect: ({ onChange }: { onChange: (id: number) => void }) => (
    <button type="button" onClick={() => onChange(3)}>
      select-category-3
    </button>
  ),
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

const fetchEffectiveAttributesMock = vi.fn()
vi.mock('@/features/product-categories/api', () => ({
  fetchEffectiveAttributes: (...args: unknown[]) => fetchEffectiveAttributesMock(...args),
}))

/**
 * Spec 0062: the layout query is a SEPARATE hook (`useProductAttributeLayout`)
 * from `useEffectiveAttributes` above — mocked independently so this suite
 * controls the layout each test sees without a real network round-trip.
 * Defaults to "no layout configured" (AC-007 flat fallback).
 */
const productAttributeLayoutMock = vi.fn()
vi.mock('@/features/products/use-product-attribute-layout', () => ({
  useProductAttributeLayout: (...args: unknown[]) => productAttributeLayoutMock(...args),
}))

const FULL_ACCESS: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

function permissions(): ResourcePermissions {
  return { resource: FULL_ACCESS, fields: {}, actions: {} }
}

const RAM_ATTRIBUTE: EffectiveAttribute = {
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
  inherited: false,
  context: 'product',
  options: [],
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
    permissions: permissions(),
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
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissions() })
  fetchEffectiveAttributesMock.mockReset()
  fetchEffectiveAttributesMock.mockResolvedValue([])
  productAttributeLayoutMock.mockReset()
  productAttributeLayoutMock.mockReturnValue({ data: null, isLoading: false })
})

describe('ProductForm — dynamic attribute fields (spec 0061)', () => {
  it('shows the empty hint before any category is picked', async () => {
    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(await screen.findByText('Select a category to see its attributes.')).toBeInTheDocument()
    expect(fetchEffectiveAttributesMock).not.toHaveBeenCalled()
  })

  it('fetches the PRODUCT context effective attributes once a category is picked and renders a control per attribute', async () => {
    fetchEffectiveAttributesMock.mockResolvedValue([RAM_ATTRIBUTE])

    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(await screen.findByText('select-category-3'))

    await waitFor(() => expect(fetchEffectiveAttributesMock).toHaveBeenCalledWith(3, 'product'))
    expect(await screen.findByRole('spinbutton', { name: 'RAM (GB)' })).toBeInTheDocument()
  })

  it('submits the filled attribute value in attribute_values', async () => {
    fetchEffectiveAttributesMock.mockResolvedValue([RAM_ATTRIBUTE])
    createProductMock.mockResolvedValue(product())

    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'ThinkPad X1' } })
    fireEvent.click(screen.getByText('select-category-3'))
    fireEvent.change(screen.getByLabelText(/^Cost/), { target: { value: '800' } })
    fireEvent.change(screen.getByLabelText(/^Price/), { target: { value: '1200' } })

    const ramField = await screen.findByRole('spinbutton', { name: 'RAM (GB)' })
    fireEvent.change(ramField, { target: { value: '16' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createProductMock).toHaveBeenCalledTimes(1))
    const payload = createProductMock.mock.calls[0][0]
    expect(payload.attribute_values).toEqual({ ram_gb: 16 })
  })

  it('seeds the attribute value from the loaded product in edit mode', async () => {
    fetchEffectiveAttributesMock.mockResolvedValue([RAM_ATTRIBUTE])

    render(
      <ProductForm
        mode={{ type: 'edit', product: product({ attribute_values: { ram_gb: 8 } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('spinbutton', { name: 'RAM (GB)' })).toHaveValue(8)
  })
})

describe('ProductForm — configured attribute layout (spec 0062 AC-014)', () => {
  it('renders the selected category attributes sectioned when a layout is configured', async () => {
    fetchEffectiveAttributesMock.mockResolvedValue([RAM_ATTRIBUTE])
    productAttributeLayoutMock.mockReturnValue({
      data: {
        sections: [
          {
            id: 's1',
            title: 'Specifications',
            description: null,
            variant: 'default',
            collapsible: false,
            default_collapsed: false,
            columns: 1,
            sort_order: 0,
            rows: [{ id: 'r1', items: [{ attribute_code: 'ram_gb', width: 'full' }] }],
          },
        ],
      },
      isLoading: false,
    })

    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(await screen.findByText('select-category-3'))

    await waitFor(() =>
      expect(productAttributeLayoutMock).toHaveBeenCalledWith(3, 'create'),
    )
    expect(await screen.findByRole('heading', { name: 'Specifications' })).toBeInTheDocument()
    expect(screen.getByRole('spinbutton', { name: 'RAM (GB)' })).toBeInTheDocument()
  })

  it('AC-007: falls back to the flat list when no layout is configured for the category', async () => {
    fetchEffectiveAttributesMock.mockResolvedValue([RAM_ATTRIBUTE])
    productAttributeLayoutMock.mockReturnValue({ data: null, isLoading: false })

    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(await screen.findByText('select-category-3'))

    expect(await screen.findByRole('spinbutton', { name: 'RAM (GB)' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Specifications' })).not.toBeInTheDocument()
  })

  it('submits attribute_values unchanged (spec 0061 payload) when a layout is configured', async () => {
    fetchEffectiveAttributesMock.mockResolvedValue([RAM_ATTRIBUTE])
    productAttributeLayoutMock.mockReturnValue({
      data: {
        sections: [
          {
            id: 's1',
            title: 'Specifications',
            description: null,
            variant: 'default',
            collapsible: false,
            default_collapsed: false,
            columns: 1,
            sort_order: 0,
            rows: [{ id: 'r1', items: [{ attribute_code: 'ram_gb', width: 'full' }] }],
          },
        ],
      },
      isLoading: false,
    })
    createProductMock.mockResolvedValue(product())

    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'ThinkPad X1' } })
    fireEvent.click(screen.getByText('select-category-3'))
    fireEvent.change(screen.getByLabelText(/^Cost/), { target: { value: '800' } })
    fireEvent.change(screen.getByLabelText(/^Price/), { target: { value: '1200' } })

    const ramField = await screen.findByRole('spinbutton', { name: 'RAM (GB)' })
    fireEvent.change(ramField, { target: { value: '16' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createProductMock).toHaveBeenCalledTimes(1))
    const payload = createProductMock.mock.calls[0][0]
    expect(payload.attribute_values).toEqual({ ram_gb: 16 })
  })
})
