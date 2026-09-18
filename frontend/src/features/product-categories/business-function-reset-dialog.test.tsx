import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import { collectDescendantsWithOwnBusinessFunction } from '@/features/product-categories/business-function-inheritance'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { ForSelectItem } from '@/features/for-select/types'

/**
 * User directive 2026-09-16: assigning a business function to a category
 * whose descendants own one makes the backend clear every one of them
 * (spec 0023 cascade-to-null). The save now stops on a confirmation dialog
 * listing exactly those categories. Same harness as
 * `product-category-business-function-field.test.tsx`: real form, real
 * picker, only the API/`useForSelect` layer mocked.
 */

const updateProductCategoryMock = vi.fn()
const fetchProductCategoryTreeMock = vi.fn<() => Promise<ProductCategoryTreeNode[]>>()

vi.mock('@/features/product-categories/api', () => ({
  createProductCategory: vi.fn(),
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
    is_reportable: false,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    simplified_offer_line: false,
    ...overrides,
  }
}

/**
 * `Electronics > Laptops` (the edited category) with a child owning a
 * business function, plus a grandchild owning one under a child that does
 * not: the cascade is recursive, the dialog must list both.
 */
function treeWithOwningDescendants(): ProductCategoryTreeNode[] {
  return [
    treeNode({
      id: 1,
      name: 'Electronics',
      children: [
        treeNode({
          id: 4,
          name: 'Laptops',
          parent_id: 1,
          children: [
            treeNode({ id: 5, name: 'Gaming', parent_id: 4, business_function_id: 3 }),
            treeNode({
              id: 6,
              name: 'Ultrabooks',
              parent_id: 4,
              children: [treeNode({ id: 7, name: 'Convertibles', parent_id: 6, business_function_id: 9 })],
            }),
          ],
        }),
      ],
    }),
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
    is_reportable: false,
    effective_is_reportable: false,
    is_reportable_source_category: null,
    report_columns: null,
    effective_report_columns: [],
    report_columns_source_category: null,
    inherited_report_columns: [],
    inherited_report_columns_source_category: null,
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

/** Renders the edit form, picks `Support` in the business-function field and saves. */
async function pickBusinessFunctionAndSave(detail = category()) {
  render(
    <ProductCategoryForm mode={{ type: 'edit', category: detail }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
    { wrapper: wrapper() },
  )

  const trigger = await screen.findByRole('combobox', { name: 'Business function' })
  fireEvent.click(trigger)
  fireEvent.click(screen.getByRole('option', { name: 'Support' }))
  fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateProductCategoryMock.mockReset()
  updateProductCategoryMock.mockResolvedValue(category())
  fetchProductCategoryTreeMock.mockReset()
  fetchProductCategoryTreeMock.mockResolvedValue(treeWithOwningDescendants())
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissivePermissions() })
  useForSelectMock.mockReset()
  useForSelectMock.mockReturnValue(queryState([{ id: 7, label: 'Support' }]))
})

describe('collectDescendantsWithOwnBusinessFunction', () => {
  it('collects every descendant owning one, at any depth, excluding the category itself', () => {
    expect(collectDescendantsWithOwnBusinessFunction(treeWithOwningDescendants(), 4)).toEqual([
      { id: 5, name: 'Gaming' },
      { id: 7, name: 'Convertibles' },
    ])
  })

  it('returns nothing for a category absent from the tree or without owning descendants', () => {
    expect(collectDescendantsWithOwnBusinessFunction(treeWithOwningDescendants(), 999)).toEqual([])
    expect(collectDescendantsWithOwnBusinessFunction(treeWithOwningDescendants(), 6)).toEqual([
      { id: 7, name: 'Convertibles' },
    ])
    expect(collectDescendantsWithOwnBusinessFunction(treeWithOwningDescendants(), 5)).toEqual([])
  })
})

describe('ProductCategoryForm — business function reset confirmation', () => {
  it('holds the save back and lists the descendants about to be cleared', async () => {
    await pickBusinessFunctionAndSave()

    const dialog = await screen.findByRole('alertdialog')
    expect(
      within(dialog).getByText('Subcategories will lose their own business function'),
    ).toBeInTheDocument()
    expect(within(dialog).getByText(/the 2 subcategories below own/)).toBeInTheDocument()
    expect(within(dialog).getByText('Gaming')).toBeInTheDocument()
    expect(within(dialog).getByText('Convertibles')).toBeInTheDocument()
    expect(within(dialog).queryByText('Ultrabooks')).not.toBeInTheDocument()
    expect(updateProductCategoryMock).not.toHaveBeenCalled()
  })

  it('saves once the reset is confirmed', async () => {
    await pickBusinessFunctionAndSave()

    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Assign and clear' }))

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ business_function_id: 7 })
  })

  it('cancelling leaves the category untouched', async () => {
    await pickBusinessFunctionAndSave()

    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(updateProductCategoryMock).not.toHaveBeenCalled()
  })

  it('saves straight away when no descendant owns a business function', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([
      treeNode({ id: 1, name: 'Electronics', children: [treeNode({ id: 4, name: 'Laptops', parent_id: 1 })] }),
    ])

    await pickBusinessFunctionAndSave()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })

  it('does not warn when the edit leaves the business function untouched', async () => {
    render(
      <ProductCategoryForm
        mode={{ type: 'edit', category: category() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'Laptops Pro' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })
})
