import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import { resolveInheritedQuoteFlag } from '@/features/product-categories/requires-quote-inheritance'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'

/**
 * The `requires_quote` flag belongs to the branch ROOT: the switch is
 * editable only while no parent is selected, and turns into a read-only
 * mirror of the root's value (naming that root) as soon as one is — live, off
 * the cached tree, not just after a save. Mirrors the business-function field
 * suite's setup: only the API layer and the tree fetch are mocked, the real
 * form/controls render.
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

vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/use-for-select')>(
    '@/features/for-select/use-for-select',
  )
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
    ...overrides,
  }
}

function category(
  overrides: Partial<ProductCategoryDetailWithPermissions> = {},
): ProductCategoryDetailWithPermissions {
  return {
    id: 4,
    name: 'Laptops',
    parent_id: null,
    parent: null,
    inherits_product_attributes: true,
    inherits_quote_attributes: true,
    description: null,
    attributes: [],
    inherited_attributes: [],
    created_at: '2026-01-01T00:00:00Z',
    business_function_id: null,
    business_function: null,
    effective_business_function: null,
    requires_quote: false,
    requires_quote_source_category: null,
    is_selectable: true,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    management_mode_source_category: null,
    single_quote_per_opportunity_source_category: null,
    manager_labels: {},
    inherits_manager_labels: true,
    inherited_manager_labels: {},
    permissions: permissivePermissions(),
    ...overrides,
  }
}

/** The quote switch, located by the label `MetaField` wires to it. */
async function findQuoteSwitch() {
  return screen.findByRole('switch', { name: 'Quoted' })
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
})

describe('resolveInheritedQuoteFlag', () => {
  const tree = [
    treeNode({
      id: 1,
      name: 'Electronics',
      requires_quote: true,
      children: [
        treeNode({
          id: 2,
          name: 'Wiring',
          parent_id: 1,
          requires_quote: true,
          children: [treeNode({ id: 3, name: 'Sockets', parent_id: 2, requires_quote: true })],
        }),
      ],
    }),
    treeNode({ id: 9, name: 'Services', requires_quote: false }),
  ]

  it('resolves the ROOT flag, not the nearest ancestor', () => {
    const nodesById = indexCategoryTree(tree)

    expect(resolveInheritedQuoteFlag(nodesById, 2)).toEqual({
      requiresQuote: true,
      sourceCategory: { id: 1, name: 'Electronics' },
    })
    expect(resolveInheritedQuoteFlag(nodesById, 3)).toEqual({
      requiresQuote: true,
      sourceCategory: { id: 1, name: 'Electronics' },
    })
  })

  it('returns null for a root pick (nothing to inherit) and for an unknown node', () => {
    const nodesById = indexCategoryTree(tree)

    expect(resolveInheritedQuoteFlag(nodesById, null)).toBeNull()
    expect(resolveInheritedQuoteFlag(nodesById, 999)).toBeNull()
  })
})

describe('ProductCategoryForm — requires_quote field', () => {
  it('root category: the switch is editable and its own hint is shown', async () => {
    render(
      <ProductCategoryForm mode={{ type: 'edit', category: category() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const quoteSwitch = await findQuoteSwitch()
    expect(quoteSwitch).not.toBeDisabled()
    expect(quoteSwitch).not.toBeChecked()
    expect(
      screen.getByText('When on, this category and every subcategory below it are quoted.'),
    ).toBeInTheDocument()
  })

  it('root category: toggling the switch sends the flag on save', async () => {
    updateProductCategoryMock.mockResolvedValue(category({ requires_quote: true }))

    render(
      <ProductCategoryForm mode={{ type: 'edit', category: category() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(await findQuoteSwitch())
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ requires_quote: true })
  })

  it('child category: the switch is read-only, mirrors the root and names it', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([
      treeNode({
        id: 1,
        name: 'Electronics',
        requires_quote: true,
        children: [treeNode({ id: 4, name: 'Laptops', parent_id: 1, requires_quote: true })],
      }),
    ])

    render(
      <ProductCategoryForm
        mode={{
          type: 'edit',
          category: category({
            parent_id: 1,
            parent: { id: 1, name: 'Electronics' },
            requires_quote: true,
            requires_quote_source_category: { id: 1, name: 'Electronics' },
          }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const quoteSwitch = await findQuoteSwitch()
    await waitFor(() => expect(quoteSwitch).toBeDisabled())
    expect(quoteSwitch).toBeChecked()
    expect(
      screen.getByText(
        'Quoting is inherited from the root category "Electronics". To change it, edit that category instead.',
      ),
    ).toBeInTheDocument()
  })

  it('child category: the flag never reaches the payload', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([
      treeNode({
        id: 1,
        name: 'Electronics',
        requires_quote: true,
        children: [treeNode({ id: 4, name: 'Laptops', parent_id: 1, requires_quote: true })],
      }),
    ])
    const child = category({
      parent_id: 1,
      parent: { id: 1, name: 'Electronics' },
      requires_quote: true,
      requires_quote_source_category: { id: 1, name: 'Electronics' },
    })
    updateProductCategoryMock.mockResolvedValue(child)

    render(
      <ProductCategoryForm mode={{ type: 'edit', category: child }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'Laptops Pro' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ name: 'Laptops Pro' })
  })

  it('create: picking a parent locks the switch on the root flag, live', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([
      treeNode({ id: 1, name: 'Electronics', requires_quote: true }),
    ])

    render(
      <ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const quoteSwitch = await findQuoteSwitch()
    expect(quoteSwitch).not.toBeDisabled()
    expect(quoteSwitch).not.toBeChecked()

    fireEvent.click(await screen.findByRole('combobox', { name: 'Parent category' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Electronics' }))

    await waitFor(() => expect(quoteSwitch).toBeDisabled())
    expect(quoteSwitch).toBeChecked()
    expect(
      screen.getByText(
        'Quoting is inherited from the root category "Electronics". To change it, edit that category instead.',
      ),
    ).toBeInTheDocument()
  })
})
