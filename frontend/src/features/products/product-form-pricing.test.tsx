import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductForm } from '@/features/products/product-form'
import type { ProductDetailWithPermissions } from '@/features/products/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0191 AC-009/AC-010/AC-012: Cost and Price follow the usages — each
 * field (and the margin) appears only when pertinent, a hidden value survives
 * the toggle, and a hidden field is neither required nor sent in a PATCH.
 */

const createProductMock = vi.fn()
const updateProductMock = vi.fn()

vi.mock('@/features/products/api', () => ({
  createProduct: (...args: unknown[]) => createProductMock(...args),
  updateProduct: (...args: unknown[]) => updateProductMock(...args),
  fetchProductNextCode: () => Promise.resolve('PRD-0100'),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/config/use-config', () => ({
  useEnumOptions: (key: string) =>
    (key === 'product_usage'
      ? [
          { value: 'SALE', label: 'Sellable' },
          { value: 'COST', label: 'Usable as cost' },
        ]
      : [{ value: 'SERVICE', label: 'Service' }]
    ).map((option) => ({ ...option, color: null, icon: null, is_default: false, hidden_on_form: false })),
}))

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: [], isPending: false, isError: false, refetch: () => {} }),
}))

/** Stubs the category picker as a button calling `onChange(3)`, mirroring `project-form-body.test.tsx`'s select stubs. */
vi.mock('@/components/ui/searchable-select', () => ({
  SearchableSelect: ({
    value,
    onChange,
    disabled,
  }: {
    value: number | null
    onChange: (id: number) => void
    disabled?: boolean
  }) => (
    <button type="button" disabled={disabled} data-testid="category-select" onClick={() => onChange(3)}>
      {value ?? ''}
    </button>
  ),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

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

const FULL_PERMISSIONS: ResourcePermissions = {
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
    usages: ['SALE'],
    created_at: '2026-01-01T00:00:00Z',
    vat_rate_id: null,
    vat_rate: null,
    supplier_id: null,
    supplier: null,
    unit_of_measure_id: 1,
    unit_of_measure: { id: 1, name: 'Unit', symbol: 'pz' },
    product_typology_id: 1,
    product_typology: { id: 1, name: 'Ente', color: 'violet' },
    permissions: FULL_PERMISSIONS,
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
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
  canMock.mockReset()
  canMock.mockReturnValue(true)
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

function fillNameAndCategory() {
  fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'ThinkPad X1' } })
  fireEvent.click(screen.getByTestId('category-select'))
}

function save() {
  fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))
}

describe('ProductForm — cost/price by usage (spec 0191)', () => {
  it('AC-009: shows only the price by default, adds the cost with the cost usage, drops the price without Sellable', async () => {
    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await screen.findByRole('checkbox', { name: 'Sellable' })
    expect(screen.getByLabelText('Price')).toBeInTheDocument()
    expect(screen.queryByLabelText('Cost')).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Usable as cost' }))
    expect(screen.getByLabelText('Cost')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Sellable' }))
    expect(screen.queryByLabelText('Price')).not.toBeInTheDocument()
  })

  it('AC-009: a cost-only product saves without a price, sending it as null', async () => {
    createProductMock.mockResolvedValue(product())
    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(await screen.findByRole('checkbox', { name: 'Usable as cost' }))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Sellable' }))
    fillNameAndCategory()
    fireEvent.change(screen.getByLabelText('Cost'), { target: { value: '800' } })
    save()

    await waitFor(() => expect(createProductMock).toHaveBeenCalledTimes(1))
    expect(createProductMock.mock.calls[0][0]).toMatchObject({ usages: ['COST'], cost: 800, price: null })
  })

  it('AC-010: a typed price is still there after Sellable is unticked and ticked again', async () => {
    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await screen.findByRole('checkbox', { name: 'Sellable' })
    fireEvent.change(screen.getByLabelText('Price'), { target: { value: '1200' } })
    fireEvent.click(screen.getByRole('checkbox', { name: 'Usable as cost' }))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Sellable' }))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Sellable' }))

    expect(screen.getByLabelText('Price')).toHaveValue(1200)
  })

  it('AC-010: dropping a usage in edit does not put cost/price in the PATCH', async () => {
    updateProductMock.mockResolvedValue(product({ usages: ['COST'] }))
    render(
      <ProductForm
        mode={{ type: 'edit', product: product({ usages: ['SALE', 'COST'] }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.click(await screen.findByRole('checkbox', { name: 'Sellable' }))
    save()

    await waitFor(() => expect(updateProductMock).toHaveBeenCalledTimes(1))
    expect(updateProductMock.mock.calls[0]).toContainEqual({ usages: ['COST'] })
  })

  it('AC-012: the margin readout and the summary rows follow the usages', async () => {
    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await screen.findByRole('checkbox', { name: 'Sellable' })
    expect(screen.queryByText('Margin')).not.toBeInTheDocument()
    expect(screen.getAllByText('Price').length).toBeGreaterThan(1)
    expect(screen.queryAllByText('Cost')).toHaveLength(0)

    fireEvent.click(screen.getByRole('checkbox', { name: 'Usable as cost' }))
    expect(screen.getAllByText('Margin').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Cost').length).toBeGreaterThan(1)

    fireEvent.click(screen.getByRole('checkbox', { name: 'Sellable' }))
    expect(screen.queryByText('Margin')).not.toBeInTheDocument()
    expect(screen.queryAllByText('Price')).toHaveLength(0)
  })
})
