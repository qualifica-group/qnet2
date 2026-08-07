import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductForm } from '@/features/products/product-form'
import type { ProductDetailWithPermissions } from '@/features/products/types'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0074 AC-015/AC-016: the product form's category picker reads the
 * STRUCTURAL tree cache, so it resolves selectability client-side. Amended by
 * the user directive of 2026-08-03: an unselectable category is no longer
 * omitted but LISTED DISABLED — it is the parent its selectable children hang
 * from. The category already saved on the product being edited stays pickable
 * (AC-016).
 */

vi.mock('@/features/products/api', () => ({
  createProduct: vi.fn(),
  updateProduct: vi.fn(),
  fetchProductNextCode: () => Promise.resolve('PRD-0100'),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

vi.mock('@/features/config/use-config', () => ({
  useEnumOptions: () => [
    { value: 'SERVICE', label: 'Service', color: null, icon: null, is_default: true, hidden_on_form: false },
  ],
}))

vi.mock('@/features/products/use-product-attribute-layout', () => ({
  useProductAttributeLayout: () => ({ data: null, isLoading: false }),
}))

const treeDataMock = vi.fn<() => ProductCategoryTreeNode[]>()
vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({
    data: treeDataMock(),
    isPending: false,
    isError: false,
    refetch: () => {},
  }),
}))

/**
 * Records the option list the category picker receives: the assertion target
 * of this suite (the real `SearchableSelect` renders its options only once
 * opened through a portal).
 */
const selectOptionsMock = vi.fn<(options: { id: number; name: string; disabled?: boolean }[]) => void>()
vi.mock('@/components/ui/searchable-select', () => ({
  SearchableSelect: ({ options }: { options: { id: number; name: string; disabled?: boolean }[] }) => {
    selectOptionsMock(options)
    return <div data-testid="category-select-stub" />
  },
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<
    typeof import('@/features/for-select/use-for-select')
  >('@/features/for-select/use-for-select')
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: () => ({
      data: { pages: [{ items: [] }] },
      isPending: false,
      isError: false,
      fetchNextPage: vi.fn(),
      hasNextPage: false,
      isFetchingNextPage: false,
      refetch: vi.fn(),
    }),
    useForSelectLabels: () => new Map(),
  }
})

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
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

function treeNode(
  overrides: Partial<ProductCategoryTreeNode> & { id: number; name: string },
): ProductCategoryTreeNode {
  return {
    parent_id: null,
    children: [],
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    is_selectable: true,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    ...overrides,
  }
}

const TREE: ProductCategoryTreeNode[] = [
  treeNode({
    id: 1,
    name: 'Container',
    is_selectable: false,
    children: [treeNode({ id: 2, name: 'Laptops', parent_id: 1 })],
  }),
]

function product(overrides: Partial<ProductDetailWithPermissions> = {}): ProductDetailWithPermissions {
  return {
    id: 5,
    code: 'PRD-0005',
    name: 'ThinkPad X1',
    description: null,
    cost: 800,
    price: 1200,
    category_id: 2,
    category: { id: 2, name: 'Laptops' },
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

/** The ids of the last option list handed to the category picker, marking the ones offered as disabled. */
function lastOptionIds(): string[] {
  const lastCall = selectOptionsMock.mock.calls.at(-1)
  return (lastCall?.[0] ?? []).map((option) => `${option.id}${option.disabled ? ':disabled' : ''}`)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_ACCESS })
  selectOptionsMock.mockReset()
  treeDataMock.mockReset()
  treeDataMock.mockReturnValue(TREE)
})

describe('ProductFormBody — category picker selectability', () => {
  it('lists an unselectable category disabled, its selectable child pickable (AC-015)', async () => {
    render(<ProductForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await screen.findByTestId('category-select-stub')
    await waitFor(() => expect(lastOptionIds()).toEqual(['1:disabled', '2']))
  })

  it('keeps the category already saved on the product being edited (AC-016)', async () => {
    render(
      <ProductForm
        mode={{ type: 'edit', product: product({ category_id: 1, category: { id: 1, name: 'Container' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findByTestId('category-select-stub')
    await waitFor(() => expect(lastOptionIds()).toEqual(['1', '2']))
  })
})
