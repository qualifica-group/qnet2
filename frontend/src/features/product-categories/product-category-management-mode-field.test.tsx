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
import { resolveInheritedManagementMode } from '@/features/product-categories/management-mode-inheritance'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * `management_mode` (spec 0077) belongs to the branch ROOT: the row only offers
 * a pencil while no parent is selected (AC-040); under a parent it shows the
 * root's value and names that root, resolved off the cached tree (live on the
 * create draft, spec 0229). Mirrors the `requires_quote` field suite's setup:
 * only the API layer and the tree fetch are mocked, the real detail/form
 * controls render.
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

/** The management-mode selector, located by the label `MetaField` wires to it. */
async function findManagementModeSelect() {
  return screen.findByRole('combobox', { name: 'Management mode' })
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

describe('resolveInheritedManagementMode', () => {
  const tree = [
    treeNode({
      id: 1,
      name: 'Electronics',
      management_mode: 'single',
      single_quote_per_opportunity: false,
      generates_contract: true,
      children: [
        treeNode({
          id: 2,
          name: 'Wiring',
          parent_id: 1,
          management_mode: 'single',
          single_quote_per_opportunity: false,
          generates_contract: true,
          children: [treeNode({ id: 3, name: 'Sockets', parent_id: 2, management_mode: 'single' })],
        }),
      ],
    }),
    treeNode({ id: 9, name: 'Services', management_mode: 'multiple' }),
  ]

  it('resolves the ROOT value, not the nearest ancestor', () => {
    const nodesById = indexCategoryTree(tree)

    expect(resolveInheritedManagementMode(nodesById, 2)).toEqual({
      managementMode: 'single',
      sourceCategory: { id: 1, name: 'Electronics' },
    })
    expect(resolveInheritedManagementMode(nodesById, 3)).toEqual({
      managementMode: 'single',
      sourceCategory: { id: 1, name: 'Electronics' },
    })
  })

  it('returns null for a root pick (nothing to inherit) and for an unknown node', () => {
    const nodesById = indexCategoryTree(tree)

    expect(resolveInheritedManagementMode(nodesById, null)).toBeNull()
    expect(resolveInheritedManagementMode(nodesById, 999)).toBeNull()
  })
})

describe('management_mode row (AC-040, spec 0229)', () => {
  it('root category: the row opens on the enabled selector with its own hint', async () => {
    renderDetail(category())

    fireEvent.click(pencilOf('managementMode') as HTMLElement)

    const select = await findManagementModeSelect()
    expect(select).not.toBeDisabled()
    expect(select).toHaveTextContent('Multiple (several lines per card)')
    expect(
      screen.getByText(
        'How Category Product lines behave on a card: this category and every subcategory below it follow the same rule.',
      ),
    ).toBeInTheDocument()
  })

  it('root category: switching the mode sends the value on save', async () => {
    updateProductCategoryMock.mockResolvedValue(category({ management_mode: 'single' }))
    renderDetail(category())

    fireEvent.click(pencilOf('managementMode') as HTMLElement)
    fireEvent.click(await findManagementModeSelect())
    fireEvent.click(await screen.findByRole('option', { name: 'Single (one line per card)' }))
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ management_mode: 'single' })
  })

  it('child category (AC-040): the mode mirrors the root, names it and offers no pencil', () => {
    renderDetail(childCategory({ management_mode: 'single' }))

    expect(pencilOf('managementMode')).not.toBeInTheDocument()
    expect(screen.getByText('Single (one line per card)')).toBeInTheDocument()
    expect(screen.getAllByText('Inherited from Electronics').length).toBeGreaterThan(0)
  })

  it('child category: the value never reaches the payload', async () => {
    const child = childCategory({ management_mode: 'single' })
    updateProductCategoryMock.mockResolvedValue(child)
    renderDetail(child)

    fireEvent.click(pencilOf('name') as HTMLElement)
    fireEvent.change(await screen.findByRole('textbox', { name: /^Name/ }), { target: { value: 'Laptops Pro' } })
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ name: 'Laptops Pro' })
  })

  it('create: a draft under a parent mirrors the root mode and offers no pencil', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([
      treeNode({ id: 1, name: 'Electronics', management_mode: 'single' }),
    ])

    render(
      <ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByText('Multiple (several lines per card)')).toBeInTheDocument()

    fireEvent.click(pencilOf('parent') as HTMLElement)
    fireEvent.click(await screen.findByRole('combobox', { name: 'Parent category' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Electronics' }))
    applyRow()

    await waitFor(() => expect(pencilOf('managementMode')).not.toBeInTheDocument())
    expect(screen.getByText('Single (one line per card)')).toBeInTheDocument()
  })
})
