import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import {
  applyRow,
  categoryWrapper,
  category as baseCategory,
  pencilOf,
  permissionsFor,
  saveRow,
  treeNode as baseTreeNode,
} from '@/features/product-categories/product-category-test-fixtures'
import type { ProductCategoryDetailWithPermissions, ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { ResourceMeta } from '@/features/authorization/types'
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

vi.mock('@/features/product-categories/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/product-categories/api')>()),
  createProductCategory: (...args: unknown[]) => createProductCategoryMock(...args),
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

// This suite isn't about the quick-create "+" (spec 0028): the picker's
// `QuickCreateButton` reads `useAbilities()` via `Can`, which needs an
// `AuthProvider` this suite doesn't render.
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
  return baseTreeNode(overrides)
}

/** Two root branches: one carries its own business function, the other does not (spec 0023 AC-018/AC-019). */
function branchingTree(): ProductCategoryTreeNode[] {
  return [
    treeNode({ id: 10, name: 'Sales Branch', business_function_id: 5 }),
    treeNode({ id: 20, name: 'No Function Branch', business_function_id: null }),
  ]
}

/** A category under "Electronics" that owns no business function (the shared root fixture, reparented). */
function category(overrides: Partial<ProductCategoryDetailWithPermissions> = {}): ProductCategoryDetailWithPermissions {
  return baseCategory({ parent_id: 1, parent: { id: 1, name: 'Electronics' }, ...overrides })
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
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
  useForSelectMock.mockReset()
  useForSelectMock.mockReturnValue(queryState())
})

describe('business function row (spec 0023 AC-015/AC-016, spec 0229)', () => {
  it('shows the inherited function with its source and offers no pencil', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([
      treeNode({ id: 1, name: 'Electronics', business_function_id: 1 }),
    ])

    renderDetail(
      category({
        effective_business_function: {
          id: 1,
          name: 'Sales',
          inherited: true,
          source_category: { id: 1, name: 'Electronics' },
        },
      }),
    )

    expect(pencilOf('businessFunction')).not.toBeInTheDocument()
    expect(screen.getAllByText('Sales').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Inherited from Electronics').length).toBeGreaterThan(0)
  })

  it('keeps the picker enabled and lets the user pick a business function when not inherited', async () => {
    useForSelectMock.mockReturnValue(queryState([{ id: 7, label: 'Support' }]))
    updateProductCategoryMock.mockResolvedValue(category())

    renderDetail(category())

    fireEvent.click(pencilOf('businessFunction') as HTMLElement)
    const trigger = await screen.findByRole('combobox', { name: 'Business function' })
    expect(trigger).not.toBeDisabled()

    fireEvent.click(trigger)
    fireEvent.click(screen.getByRole('option', { name: 'Support' }))
    saveRow()

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

    renderDetail(inheriting)

    fireEvent.click(pencilOf('name') as HTMLElement)
    fireEvent.change(await screen.findByLabelText(/^Name/), { target: { value: 'Laptops Pro' } })
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ name: 'Laptops Pro' })
    expect(payload).not.toHaveProperty('business_function_id')
  })
})

describe('business function row follows the parent (spec 0023 AC-019)', () => {
  it('create: choosing a parent that carries a function makes the row inherited, without a pencil', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue(branchingTree())

    render(
      <ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('button', { name: 'Edit Business function' })).toBeInTheDocument()

    fireEvent.click(pencilOf('parent') as HTMLElement)
    fireEvent.click(await findParentTrigger())
    fireEvent.click(await screen.findByRole('option', { name: 'Sales Branch' }))
    applyRow()

    await waitFor(() => expect(pencilOf('businessFunction')).not.toBeInTheDocument())
    expect(screen.getAllByText('Inherited from Sales Branch').length).toBeGreaterThan(0)
  })

  it('create: choosing a parent without a function leaves the row editable', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue(branchingTree())

    render(
      <ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Parent category' }))
    fireEvent.click(await findParentTrigger())
    fireEvent.click(await screen.findByRole('option', { name: 'No Function Branch' }))
    applyRow()

    expect(await screen.findByRole('button', { name: 'Edit Business function' })).toBeInTheDocument()
  })

  it('edit: moving the category sends the parent alone, the server re-aligns the function', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue(branchingTree())
    updateProductCategoryMock.mockResolvedValue(category({ parent_id: 10, parent: { id: 10, name: 'Sales Branch' } }))

    renderDetail(category({ parent_id: 20, parent: { id: 20, name: 'No Function Branch' } }))

    fireEvent.click(pencilOf('parent') as HTMLElement)
    fireEvent.click(await findParentTrigger())
    fireEvent.click(await screen.findByRole('option', { name: 'Sales Branch' }))
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ parent_id: 10 })
  })
})
