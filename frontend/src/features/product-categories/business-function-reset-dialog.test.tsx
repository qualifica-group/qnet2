import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import {
  categoryWrapper,
  category as baseCategory,
  pencilOf,
  permissionsFor,
  saveRow,
} from '@/features/product-categories/product-category-test-fixtures'
import { collectDescendantsWithOwnBusinessFunction } from '@/features/product-categories/business-function-inheritance'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'
import type { ResourceMeta } from '@/features/authorization/types'
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

vi.mock('@/features/product-categories/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/product-categories/api')>()),
  createProductCategory: vi.fn(),
  updateProductCategory: (...args: unknown[]) => updateProductCategoryMock(...args),
  fetchProductCategoryTree: () => fetchProductCategoryTreeMock(),
  fetchEffectiveAttributes: () => Promise.resolve([]),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

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

function wrapper() {
  return categoryWrapper().wrapper
}

function renderDetail(detail: ProductCategoryDetailWithPermissions) {
  return render(<ProductCategoryDetailView category={detail} />, { wrapper: wrapper() })
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

/** A category under "Electronics" that owns no business function (the shared root fixture, reparented). */
function category(overrides: Partial<ProductCategoryDetailWithPermissions> = {}): ProductCategoryDetailWithPermissions {
  return baseCategory({ id: 4, parent_id: 1, parent: { id: 1, name: 'Electronics' }, ...overrides })
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

/** Renders the detail, opens the business function row, picks `Support` and saves. */
async function pickBusinessFunctionAndSave(detail = category()) {
  renderDetail(detail)

  fireEvent.click(pencilOf('businessFunction') as HTMLElement)
  const trigger = await screen.findByRole('combobox', { name: 'Business function' })
  fireEvent.click(trigger)
  fireEvent.click(screen.getByRole('option', { name: 'Support' }))
  saveRow()
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
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
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

describe('business function reset confirmation (in place, spec 0229)', () => {
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
    renderDetail(category())

    fireEvent.click(pencilOf('name') as HTMLElement)
    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'Laptops Pro' } })
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })
})
