import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import type { ProductCategoryDetailWithPermissions, ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { ForSelectItem } from '@/features/for-select/types'

/**
 * Spec 0023 AC-015/AC-016/AC-018/AC-019: the category form's business
 * function picker is disabled and shows the inherited value + source
 * category whenever the SELECTED parent (live, not just the saved one)
 * carries one — resolved by walking the cached category tree, which now
 * exposes each node's own `business_function_id` (AC-018). Uses the real
 * `AsyncPaginatedSelect`/`SearchableSelect` — only the underlying
 * `useForSelect` hook and the tree fetch are mocked (mirrors
 * `async-paginated-select.test.tsx`), so the assertions exercise the actual
 * component wiring, not a stand-in.
 */

const createProductCategoryMock = vi.fn()
const updateProductCategoryMock = vi.fn()
const fetchProductCategoryTreeMock = vi.fn<() => Promise<ProductCategoryTreeNode[]>>()

vi.mock('@/features/product-categories/api', () => ({
  createProductCategory: (...args: unknown[]) => createProductCategoryMock(...args),
  updateProductCategory: (...args: unknown[]) => updateProductCategoryMock(...args),
  fetchProductCategoryTree: () => fetchProductCategoryTreeMock(),
  fetchEffectiveAttributes: () => Promise.resolve([]),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const useForSelectMock = vi.fn()
vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/use-for-select')>(
    '@/features/for-select/use-for-select',
  )
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: (args: unknown) => useForSelectMock(args),
    useForSelectLabels: () => new Map(),
  }
})

// This suite isn't about the quick-create "+" (spec 0028): the picker's
// `QuickCreateButton` reads `useAbilities()` via `Can`, which needs an
// `AuthProvider` this suite doesn't render.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const FULL_ACCESS: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

function permissivePermissions(): ResourcePermissions {
  return { resource: FULL_ACCESS, fields: {}, actions: {} }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function treeNode(overrides: Partial<ProductCategoryTreeNode> = {}): ProductCategoryTreeNode {
  return {
    id: 1,
    name: 'Node',
    parent_id: null,
    children: [],
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    is_selectable: true,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    simplified_offer_line: false,
    ...overrides,
  }
}

/** Two root branches: one carries its own business function, the other does not (spec 0023 AC-018/AC-019). */
function branchingTree(): ProductCategoryTreeNode[] {
  return [
    treeNode({ id: 10, name: 'Sales Branch', business_function_id: 5 }),
    treeNode({ id: 20, name: 'No Function Branch', business_function_id: null }),
  ]
}

function category(
  overrides: Partial<ProductCategoryDetailWithPermissions> = {},
): ProductCategoryDetailWithPermissions {
  return {
    id: 4,
    name: 'Laptops',
    parent_id: 1,
    parent: { id: 1, name: 'Electronics' },
    inherits_product_attributes: true,
    inherits_quote_attributes: true,
    inherits_work_order_attributes: true,
    description: null,
    attributes: [],
    inherited_attributes: [],
    created_at: '2026-01-01T00:00:00Z',
    business_function_id: null,
    requires_quote: false,
    business_function: null,
    effective_business_function: null,
    requires_quote_source_category: null,
    is_selectable: true,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    management_mode_source_category: null,
    single_quote_per_opportunity_source_category: null,
    generates_contract_source_category: null,
    simplified_offer_line: false,
    simplified_offer_line_source_category: null,
    manager_labels: {},
    inherits_manager_labels: true,
    inherited_manager_labels: {},
    permissions: permissivePermissions(),
    ...overrides,
  }
}

/** The parent category picker (`SearchableSelect`), located by its accessible name. */
async function findParentTrigger() {
  return screen.findByRole('combobox', { name: 'Parent category' })
}

function queryState(items: ForSelectItem[] = []) {
  return {
    data: { pages: [{ items }] },
    isPending: false,
    isError: false,
    fetchNextPage: vi.fn(),
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createProductCategoryMock.mockReset()
  updateProductCategoryMock.mockReset()
  fetchProductCategoryTreeMock.mockReset()
  fetchProductCategoryTreeMock.mockResolvedValue([])
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissivePermissions() })
  useForSelectMock.mockReset()
  useForSelectMock.mockReturnValue(queryState())
})

describe('ProductCategoryForm — business function field (spec 0023 AC-015/AC-016)', () => {
  it('disables the picker and shows the inherited hint when the category inherits one', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([
      treeNode({ id: 1, name: 'Electronics', business_function_id: 1 }),
    ])

    render(
      <ProductCategoryForm
        mode={{
          type: 'edit',
          category: category({
            effective_business_function: {
              id: 1,
              name: 'Sales',
              inherited: true,
              source_category: { id: 1, name: 'Electronics' },
            },
          }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const trigger = await screen.findByRole('combobox', { name: 'Business function' })
    await waitFor(() => expect(trigger).toBeDisabled())
    expect(screen.getByText('Sales')).toBeInTheDocument()
    expect(
      screen.getByText('Inherited from "Electronics". To change it, edit that category instead.'),
    ).toBeInTheDocument()
  })

  it('keeps the picker enabled and lets the user pick a business function when not inherited', async () => {
    useForSelectMock.mockReturnValue(queryState([{ id: 7, label: 'Support' }]))
    updateProductCategoryMock.mockResolvedValue(category())

    render(
      <ProductCategoryForm mode={{ type: 'edit', category: category() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const trigger = await screen.findByRole('combobox', { name: 'Business function' })
    expect(trigger).not.toBeDisabled()

    fireEvent.click(trigger)
    fireEvent.click(screen.getByRole('option', { name: 'Support' }))
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ business_function_id: 7 })
  })

  it('never sends business_function_id in the payload while the category inherits one', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([
      treeNode({ id: 1, name: 'Electronics', business_function_id: 1 }),
    ])
    const inheriting = category({
      name: 'Laptops',
      effective_business_function: {
        id: 1,
        name: 'Sales',
        inherited: true,
        source_category: { id: 1, name: 'Electronics' },
      },
    })
    updateProductCategoryMock.mockResolvedValue(inheriting)

    render(
      <ProductCategoryForm mode={{ type: 'edit', category: inheriting }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'Laptops Pro' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ name: 'Laptops Pro' })
    expect(payload).not.toHaveProperty('business_function_id')
  })
})

describe('ProductCategoryForm — business function field reacts to the SELECTED parent (spec 0023 AC-019)', () => {
  it('(a) create: choosing a parent that carries a function disables the picker and shows its source', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue(branchingTree())

    render(
      <ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const parentTrigger = await findParentTrigger()
    expect(await screen.findByRole('combobox', { name: 'Business function' })).not.toBeDisabled()

    fireEvent.click(parentTrigger)
    fireEvent.click(await screen.findByRole('option', { name: 'Sales Branch' }))

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Business function' })).toBeDisabled(),
    )
    expect(
      screen.getByText('Inherited from "Sales Branch". To change it, edit that category instead.'),
    ).toBeInTheDocument()
  })

  it('(b) edit: moving the category under a branch with a function disables the picker', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue(branchingTree())

    render(
      <ProductCategoryForm
        mode={{
          type: 'edit',
          category: category({ parent_id: 20, parent: { id: 20, name: 'No Function Branch' } }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const parentTrigger = await findParentTrigger()
    expect(await screen.findByRole('combobox', { name: 'Business function' })).not.toBeDisabled()

    fireEvent.click(parentTrigger)
    fireEvent.click(await screen.findByRole('option', { name: 'Sales Branch' }))

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Business function' })).toBeDisabled(),
    )
    expect(
      screen.getByText('Inherited from "Sales Branch". To change it, edit that category instead.'),
    ).toBeInTheDocument()
  })

  it('(c) edit: moving the category under a branch without a function re-enables the picker', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue(branchingTree())

    render(
      <ProductCategoryForm
        mode={{
          type: 'edit',
          category: category({ parent_id: 10, parent: { id: 10, name: 'Sales Branch' } }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const parentTrigger = await findParentTrigger()
    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Business function' })).toBeDisabled(),
    )

    fireEvent.click(parentTrigger)
    fireEvent.click(await screen.findByRole('option', { name: 'No Function Branch' }))

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Business function' })).not.toBeDisabled(),
    )
    expect(
      screen.queryByText('Inherited from "Sales Branch". To change it, edit that category instead.'),
    ).not.toBeInTheDocument()
  })
})
