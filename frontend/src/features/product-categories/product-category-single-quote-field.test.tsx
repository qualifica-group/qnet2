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
import { resolveInheritedSingleQuoteFlag } from '@/features/product-categories/single-quote-inheritance'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * `single_quote_per_opportunity` (user directive 2026-08-07) belongs to the
 * branch ROOT: the row only offers a pencil while no parent is selected; under a
 * parent it shows the root's value and names that root, resolved off the cached
 * tree (live on the create draft, spec 0229). Mirrors the `requires_quote`
 * suite's setup: only the API layer and the tree fetch are mocked, the real
 * detail/form controls render.
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

/** The single-offer switch, located by the label `MetaField` wires to it. */
async function findSingleQuoteSwitch() {
  return screen.findByRole('switch', { name: 'One offer per opportunity' })
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

describe('resolveInheritedSingleQuoteFlag', () => {
  const tree = [
    treeNode({
      id: 1,
      name: 'Electronics',
      single_quote_per_opportunity: true,
      generates_contract: true,
      multi_quote_work_order: false,
      children: [
        treeNode({
          id: 2,
          name: 'Wiring',
          parent_id: 1,
          single_quote_per_opportunity: true,
          generates_contract: true,
          multi_quote_work_order: false,
          children: [
            treeNode({ id: 3, name: 'Sockets', parent_id: 2, single_quote_per_opportunity: true }),
          ],
        }),
      ],
    }),
    treeNode({ id: 9, name: 'Services', single_quote_per_opportunity: false }),
  ]

  it('resolves the ROOT flag, not the nearest ancestor', () => {
    const nodesById = indexCategoryTree(tree)

    expect(resolveInheritedSingleQuoteFlag(nodesById, 2)).toEqual({
      singleQuotePerOpportunity: true,
      sourceCategory: { id: 1, name: 'Electronics' },
    })
    expect(resolveInheritedSingleQuoteFlag(nodesById, 3)).toEqual({
      singleQuotePerOpportunity: true,
      sourceCategory: { id: 1, name: 'Electronics' },
    })
  })

  it('returns null for a root pick (nothing to inherit) and for an unknown node', () => {
    const nodesById = indexCategoryTree(tree)

    expect(resolveInheritedSingleQuoteFlag(nodesById, null)).toBeNull()
    expect(resolveInheritedSingleQuoteFlag(nodesById, 999)).toBeNull()
  })
})

describe('single_quote_per_opportunity row (spec 0229)', () => {
  it('root category: the row opens on the editable switch with its own hint', async () => {
    renderDetail(category())

    fireEvent.click(pencilOf('singleQuotePerOpportunity') as HTMLElement)

    const control = await findSingleQuoteSwitch()
    expect(control).not.toBeDisabled()
    expect(control).not.toBeChecked()
    expect(screen.getByText('When on, an opportunity on this category accepts a single offer.')).toBeInTheDocument()
  })

  it('root category: toggling the switch sends the flag on save', async () => {
    updateProductCategoryMock.mockResolvedValue(category({ single_quote_per_opportunity: true }))
    renderDetail(category())

    fireEvent.click(pencilOf('singleQuotePerOpportunity') as HTMLElement)
    fireEvent.click(await findSingleQuoteSwitch())
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ single_quote_per_opportunity: true })
  })

  it('child category: the rule mirrors the root, names it and offers no pencil', () => {
    renderDetail(childCategory({ single_quote_per_opportunity: true }))

    expect(pencilOf('singleQuotePerOpportunity')).not.toBeInTheDocument()
    expect(screen.getAllByText('Inherited from Electronics').length).toBeGreaterThan(0)
  })

  it('child category: the value never reaches the payload', async () => {
    const child = childCategory({ single_quote_per_opportunity: true })
    updateProductCategoryMock.mockResolvedValue(child)
    renderDetail(child)

    fireEvent.click(pencilOf('name') as HTMLElement)
    fireEvent.change(await screen.findByRole('textbox', { name: /^Name/ }), { target: { value: 'Laptops Pro' } })
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ name: 'Laptops Pro' })
  })

  it('explains the rule behind the (i) glyph', async () => {
    renderDetail(category())

    fireEvent.click(pencilOf('singleQuotePerOpportunity') as HTMLElement)

    expect(await screen.findByRole('button', { name: 'More info about One offer per opportunity' })).toBeInTheDocument()
  })

  it('create: a draft under a parent mirrors the root value and offers no pencil', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([
      treeNode({ id: 1, name: 'Electronics', single_quote_per_opportunity: true }),
    ])

    render(
      <ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('button', { name: 'Edit One offer per opportunity' })).toBeInTheDocument()

    fireEvent.click(pencilOf('parent') as HTMLElement)
    fireEvent.click(await screen.findByRole('combobox', { name: 'Parent category' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Electronics' }))
    applyRow()

    await waitFor(() => expect(pencilOf('singleQuotePerOpportunity')).not.toBeInTheDocument())
    expect(screen.getAllByText('Inherited from Electronics').length).toBeGreaterThan(0)
  })
})
