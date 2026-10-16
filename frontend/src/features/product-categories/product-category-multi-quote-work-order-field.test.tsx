import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import {
  applyRow,
  category,
  categoryWrapper,
  childCategory,
  pencilOf,
  permissionsFor,
  saveRow,
  treeNode,
} from '@/features/product-categories/product-category-test-fixtures'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import { resolveInheritedMultiQuoteWorkOrderFlag } from '@/features/product-categories/multi-quote-work-order-inheritance'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * `multi_quote_work_order` (spec 0267, AC-017) belongs to the branch ROOT: the row only offers
 * a pencil while no parent is selected; under a parent it shows the root's value and names
 * that root. Same setup as the `generates_contract` suite: only the API layer and the tree
 * fetch are mocked, the real form/controls render.
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

function wrapper() {
  return categoryWrapper().wrapper
}

function renderDetail(detail: ProductCategoryDetailWithPermissions) {
  return render(<ProductCategoryDetailView category={detail} />, { wrapper: wrapper() })
}

/** The multi-offer switch, located by the label `MetaField` wires to it. */
async function findMultiQuoteSwitch() {
  return screen.findByRole('switch', { name: 'Multi-offer work order' })
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
})

describe('resolveInheritedMultiQuoteWorkOrderFlag', () => {
  const tree = [
    treeNode({
      id: 1,
      name: 'Electronics',
      multi_quote_work_order: true,
      children: [treeNode({ id: 2, name: 'Wiring', parent_id: 1, multi_quote_work_order: true })],
    }),
  ]

  it('resolves the ROOT flag for a child pick and null for a root pick', () => {
    const nodesById = indexCategoryTree(tree)

    expect(resolveInheritedMultiQuoteWorkOrderFlag(nodesById, 2)).toEqual({
      multiQuoteWorkOrder: true,
      sourceCategory: { id: 1, name: 'Electronics' },
    })
    expect(resolveInheritedMultiQuoteWorkOrderFlag(nodesById, null)).toBeNull()
  })
})

describe('multi_quote_work_order row (spec 0267)', () => {
  it('root category: renders unchecked from the record and is editable', async () => {
    renderDetail(category({ multi_quote_work_order: false }))

    fireEvent.click(pencilOf('multiQuoteWorkOrder') as HTMLElement)

    const control = await findMultiQuoteSwitch()
    expect(control).not.toBeDisabled()
    expect(control).not.toBeChecked()
    expect(screen.getByText(/Lets you schedule from the Contracts table/)).toBeInTheDocument()
  })

  it('root category: renders checked from the record and toggling sends the flag on save', async () => {
    updateProductCategoryMock.mockResolvedValue(category({ multi_quote_work_order: false }))
    renderDetail(category({ multi_quote_work_order: true }))

    fireEvent.click(pencilOf('multiQuoteWorkOrder') as HTMLElement)
    const control = await findMultiQuoteSwitch()
    expect(control).toBeChecked()
    fireEvent.click(control)
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ multi_quote_work_order: false })
  })

  it('child category: the rule mirrors the root, names it and offers no pencil', () => {
    renderDetail(childCategory({ multi_quote_work_order: true }))

    expect(pencilOf('multiQuoteWorkOrder')).not.toBeInTheDocument()
    expect(screen.getAllByText('Inherited from Electronics').length).toBeGreaterThan(0)
  })

  it('child category: the value never reaches the payload', async () => {
    const child = childCategory({ multi_quote_work_order: true })
    updateProductCategoryMock.mockResolvedValue(child)
    renderDetail(child)

    fireEvent.click(pencilOf('name') as HTMLElement)
    fireEvent.change(await screen.findByRole('textbox', { name: /^Name/ }), { target: { value: 'Laptops Pro' } })
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ name: 'Laptops Pro' })
  })

  it('create: a draft under a parent mirrors the root value and offers no pencil', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([
      treeNode({ id: 1, name: 'Electronics', multi_quote_work_order: true }),
    ])

    render(
      <ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('button', { name: 'Edit Multi-offer work order' })).toBeInTheDocument()

    fireEvent.click(pencilOf('parent') as HTMLElement)
    fireEvent.click(await screen.findByRole('combobox', { name: 'Parent category' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Electronics' }))
    applyRow()

    await waitFor(() => expect(pencilOf('multiQuoteWorkOrder')).not.toBeInTheDocument())
    expect(screen.getAllByText('Inherited from Electronics').length).toBeGreaterThan(0)
  })
})
