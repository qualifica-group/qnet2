import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import {
  resolveInheritedSimplifiedOfferLine,
  simplifiedOfferLineOverrideFor,
} from '@/features/product-categories/simplified-offer-line-inheritance'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'

/**
 * `simplified_offer_line` (spec 0114, 0188): a ROOT declares it itself (and
 * the payload sends the value); a child inherits the nearest declaring
 * ancestor's value, shows the EFFECTIVE one and can force it (payload sends
 * the override, null when it matches the inherited value) — live, off the
 * cached tree. Only the API layer and the tree fetch are mocked.
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
    is_active: true,
    is_reportable: false,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    simplified_offer_line: false,
    simplified_offer_line_override: null,
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
    is_active: true,
    is_reportable: false,
    effective_is_reportable: false,
    is_reportable_source_category: null,
    report_columns: null,
    effective_report_columns: [],
    report_columns_source_category: null,
    inherited_report_columns: [],
    inherited_report_columns_source_category: null,
    management_mode: 'multiple',
    management_mode_source_category: null,
    single_quote_per_opportunity: false,
    single_quote_per_opportunity_source_category: null,
    generates_contract: true,
    generates_contract_source_category: null,
    simplified_offer_line: false,
    simplified_offer_line_override: null,
    simplified_offer_line_source_category: null,
    manager_labels: {},
    inherits_manager_labels: true,
    inherited_manager_labels: {},
    permissions: permissivePermissions(),
    ...overrides,
  }
}

/** The rule tile hosting a switch: badges are asserted there, not page-wide (other tiles show their own chips). */
function tileOf(control: HTMLElement) {
  const tile = control.closest<HTMLElement>('div.rounded-lg')
  if (!tile) throw new Error('rule tile not found')
  return tile
}

/** The simplified-offer-line switch, located by the label `MetaField` wires to it. */
async function findSimplifiedOfferLineSwitch() {
  return screen.findByRole('switch', { name: 'Simplified offer line' })
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

describe('resolveInheritedSimplifiedOfferLine', () => {
  const tree = [
    treeNode({
      id: 1,
      name: 'Training',
      simplified_offer_line: true,
      children: [
        treeNode({
          id: 2,
          name: 'E-Campus',
          parent_id: 1,
          simplified_offer_line: false,
          simplified_offer_line_override: false,
          children: [treeNode({ id: 3, name: 'Courses', parent_id: 2, simplified_offer_line: false })],
        }),
        treeNode({ id: 5, name: 'Seminars', parent_id: 1, simplified_offer_line: true }),
      ],
    }),
  ]

  it('takes the parent effective value and the root as source when nothing overrides', () => {
    expect(resolveInheritedSimplifiedOfferLine(indexCategoryTree(tree), 5)).toEqual({
      value: true,
      sourceCategory: { id: 1, name: 'Training' },
    })
  })

  it('stops at the nearest ancestor that overrides', () => {
    const nodesById = indexCategoryTree(tree)

    expect(resolveInheritedSimplifiedOfferLine(nodesById, 2)).toEqual({
      value: false,
      sourceCategory: { id: 2, name: 'E-Campus' },
    })
    expect(resolveInheritedSimplifiedOfferLine(nodesById, 3)).toEqual({
      value: false,
      sourceCategory: { id: 2, name: 'E-Campus' },
    })
  })

  it('returns null for a root pick and for an unknown node', () => {
    const nodesById = indexCategoryTree(tree)

    expect(resolveInheritedSimplifiedOfferLine(nodesById, null)).toBeNull()
    expect(resolveInheritedSimplifiedOfferLine(nodesById, 999)).toBeNull()
  })

  it('stores null when the switch matches the inherited value, the value otherwise', () => {
    const inherited = { value: true, sourceCategory: { id: 1, name: 'Training' } }

    expect(simplifiedOfferLineOverrideFor(true, inherited)).toBeNull()
    expect(simplifiedOfferLineOverrideFor(false, inherited)).toBe(false)
  })
})

const TRAINING_TREE = [
  treeNode({
    id: 1,
    name: 'Training',
    simplified_offer_line: true,
    children: [treeNode({ id: 4, name: 'Laptops', parent_id: 1, simplified_offer_line: true })],
  }),
]

function childCategory(overrides: Partial<ProductCategoryDetailWithPermissions> = {}) {
  return category({
    parent_id: 1,
    parent: { id: 1, name: 'Training' },
    simplified_offer_line: true,
    simplified_offer_line_override: null,
    simplified_offer_line_source_category: { id: 1, name: 'Training' },
    ...overrides,
  })
}

async function saveEdit() {
  fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))
  await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
  return updateProductCategoryMock.mock.calls[0][1]
}

describe('ProductCategoryForm — simplified_offer_line field', () => {
  it('AC-013 root: editable, and saving sends the flag, never the override', async () => {
    updateProductCategoryMock.mockResolvedValue(category({ simplified_offer_line: true }))

    render(
      <ProductCategoryForm mode={{ type: 'edit', category: category() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const simplifiedSwitch = await findSimplifiedOfferLineSwitch()
    expect(simplifiedSwitch).not.toBeDisabled()
    expect(simplifiedSwitch).not.toBeChecked()
    expect(
      screen.getByText(
        'When on, in Gestione Richieste the operator picks only the product: quantity, unit price and VAT rate of the row are filled in automatically by the system.',
      ),
    ).toBeInTheDocument()

    fireEvent.click(simplifiedSwitch)
    const payload = await saveEdit()
    expect(payload).toEqual({ simplified_offer_line: true })
    expect(payload).not.toHaveProperty('simplified_offer_line_override')
  })

  it('AC-012 child: editable, shows the effective value and the inherited badge', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue(TRAINING_TREE)

    render(
      <ProductCategoryForm mode={{ type: 'edit', category: childCategory() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const simplifiedSwitch = await findSimplifiedOfferLineSwitch()
    await waitFor(() => expect(simplifiedSwitch).not.toBeDisabled())
    expect(simplifiedSwitch).toBeChecked()
    expect(within(tileOf(simplifiedSwitch)).getByText('Inherited from Training')).toBeInTheDocument()
    expect(within(tileOf(simplifiedSwitch)).queryByText('Forced')).not.toBeInTheDocument()
  })

  it('AC-012 child: switching off sends override false, switching back on sends null', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue(TRAINING_TREE)
    updateProductCategoryMock.mockResolvedValue(childCategory())

    const { unmount } = render(
      <ProductCategoryForm mode={{ type: 'edit', category: childCategory() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const simplifiedSwitch = await findSimplifiedOfferLineSwitch()
    await waitFor(() => expect(simplifiedSwitch).not.toBeDisabled())
    fireEvent.click(simplifiedSwitch)

    expect(simplifiedSwitch).not.toBeChecked()
    expect(within(tileOf(simplifiedSwitch)).getByText('Forced')).toBeInTheDocument()
    expect(await saveEdit()).toEqual({ simplified_offer_line_override: false })
    unmount()
    updateProductCategoryMock.mockClear()

    render(
      <ProductCategoryForm
        mode={{
          type: 'edit',
          category: childCategory({
            simplified_offer_line: false,
            simplified_offer_line_override: false,
            simplified_offer_line_source_category: null,
          }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const forcedSwitch = await findSimplifiedOfferLineSwitch()
    await waitFor(() => expect(forcedSwitch).not.toBeDisabled())
    expect(forcedSwitch).not.toBeChecked()
    expect(within(tileOf(forcedSwitch)).getByText('Forced')).toBeInTheDocument()
    fireEvent.click(forcedSwitch)

    expect(forcedSwitch).toBeChecked()
    expect(within(tileOf(forcedSwitch)).getByText('Inherited from Training')).toBeInTheDocument()
    expect(await saveEdit()).toEqual({ simplified_offer_line_override: null })
  })

  it('explains the rule behind the (i) glyph', async () => {
    render(
      <ProductCategoryForm mode={{ type: 'edit', category: category() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(
      await screen.findByRole('button', { name: 'More info about Simplified offer line' }),
    ).toBeInTheDocument()
  })
})
