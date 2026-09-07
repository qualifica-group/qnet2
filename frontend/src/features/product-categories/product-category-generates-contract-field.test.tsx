import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import { resolveInheritedContractGenerationFlag } from '@/features/product-categories/contract-generation-inheritance'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'

/**
 * `generates_contract` (spec 0091) belongs to the branch ROOT: the switch is
 * editable only while no parent is selected, and turns into a read-only
 * mirror of the root's value (naming that root) as soon as one is — live, off
 * the cached tree, not just after a save. Mirrors the
 * `single_quote_per_opportunity` suite's setup: only the API layer and the
 * tree fetch are mocked, the real form/controls render.
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
    generates_contract: true,
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
    inherits_work_order_attributes: true,
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
    management_mode_source_category: null,
    single_quote_per_opportunity: false,
    generates_contract: true,
    single_quote_per_opportunity_source_category: null,
    generates_contract_source_category: null,
    manager_labels: {},
    inherits_manager_labels: true,
    inherited_manager_labels: {},
    permissions: permissivePermissions(),
    ...overrides,
  }
}

/** The contract switch, located by the label `MetaField` wires to it. */
async function findGeneratesContractSwitch() {
  return screen.findByRole('switch', { name: 'Includes a contract' })
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

describe('resolveInheritedContractGenerationFlag', () => {
  const tree = [
    treeNode({
      id: 1,
      name: 'Electronics',
      generates_contract: false,
      children: [
        treeNode({
          id: 2,
          name: 'Wiring',
          parent_id: 1,
          generates_contract: false,
          children: [treeNode({ id: 3, name: 'Sockets', parent_id: 2, generates_contract: false })],
        }),
      ],
    }),
    treeNode({ id: 9, name: 'Services', generates_contract: true }),
  ]

  it('resolves the ROOT flag, not the nearest ancestor', () => {
    const nodesById = indexCategoryTree(tree)

    expect(resolveInheritedContractGenerationFlag(nodesById, 2)).toEqual({
      generatesContract: false,
      sourceCategory: { id: 1, name: 'Electronics' },
    })
    expect(resolveInheritedContractGenerationFlag(nodesById, 3)).toEqual({
      generatesContract: false,
      sourceCategory: { id: 1, name: 'Electronics' },
    })
  })

  it('returns null for a root pick (nothing to inherit) and for an unknown node', () => {
    const nodesById = indexCategoryTree(tree)

    expect(resolveInheritedContractGenerationFlag(nodesById, null)).toBeNull()
    expect(resolveInheritedContractGenerationFlag(nodesById, 999)).toBeNull()
  })
})

describe('ProductCategoryForm — generates_contract field', () => {
  it('root category: the switch is editable, on by default, and its own hint is shown', async () => {
    render(
      <ProductCategoryForm mode={{ type: 'edit', category: category() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const contractSwitch = await findGeneratesContractSwitch()
    expect(contractSwitch).not.toBeDisabled()
    expect(contractSwitch).toBeChecked()
    expect(
      screen.getByText(
        'When on, an offer of this category that closes with a positive outcome opens a contract.',
      ),
    ).toBeInTheDocument()
  })

  it('root category: turning the switch off sends the flag on save', async () => {
    updateProductCategoryMock.mockResolvedValue(category({ generates_contract: false }))

    render(
      <ProductCategoryForm mode={{ type: 'edit', category: category() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(await findGeneratesContractSwitch())
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ generates_contract: false })
  })

  it('child category: the switch is read-only, mirrors the root and names it', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([
      treeNode({
        id: 1,
        name: 'Electronics',
        generates_contract: false,
        children: [treeNode({ id: 4, name: 'Laptops', parent_id: 1, generates_contract: false })],
      }),
    ])

    render(
      <ProductCategoryForm
        mode={{
          type: 'edit',
          category: category({
            parent_id: 1,
            parent: { id: 1, name: 'Electronics' },
            generates_contract: false,
            generates_contract_source_category: { id: 1, name: 'Electronics' },
          }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const contractSwitch = await findGeneratesContractSwitch()
    await waitFor(() => expect(contractSwitch).toBeDisabled())
    expect(contractSwitch).not.toBeChecked()
    expect(
      screen.getByText(
        'The contract rule is inherited from the root category "Electronics". To change it, edit that category instead.',
      ),
    ).toBeInTheDocument()
  })

  it('explains the rule behind the (i) glyph', async () => {
    render(
      <ProductCategoryForm mode={{ type: 'edit', category: category() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(
      await screen.findByRole('button', { name: 'More info about Includes a contract' }),
    ).toBeInTheDocument()
  })
})
