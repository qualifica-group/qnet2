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
import { resolveInheritedContractGenerationFlag } from '@/features/product-categories/contract-generation-inheritance'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * `generates_contract` (spec 0091) belongs to the branch ROOT: the row only offers a pencil
 * while no parent is selected; under a parent it shows the root's value and names that
 * root, resolved off the cached tree (live on the create draft, spec 0229). Mirrors the
 * `single_quote_per_opportunity` suite's setup: only the API layer and the
 * tree fetch are mocked, the real form/controls render.
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
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('resolveInheritedContractGenerationFlag', () => {
  const tree = [
    treeNode({
      id: 1,
      name: 'Electronics',
      generates_contract: false,
      multi_quote_work_order: false,
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

describe('generates_contract row (spec 0229)', () => {
  it('root category: the row opens on the editable switch with its own hint', async () => {
    renderDetail(category())

    fireEvent.click(pencilOf('generatesContract') as HTMLElement)

    const control = await findGeneratesContractSwitch()
    expect(control).not.toBeDisabled()
    expect(control).toBeChecked()
    expect(screen.getByText('When on, an offer of this category that closes with a positive outcome opens a contract.')).toBeInTheDocument()
  })

  it('root category: toggling the switch sends the flag on save', async () => {
    updateProductCategoryMock.mockResolvedValue(category({ generates_contract: false }))
    renderDetail(category())

    fireEvent.click(pencilOf('generatesContract') as HTMLElement)
    fireEvent.click(await findGeneratesContractSwitch())
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ generates_contract: false })
  })

  it('child category: the rule mirrors the root, names it and offers no pencil', () => {
    renderDetail(childCategory({ generates_contract: false }))

    expect(pencilOf('generatesContract')).not.toBeInTheDocument()
    expect(screen.getAllByText('Inherited from Electronics').length).toBeGreaterThan(0)
  })

  it('child category: the value never reaches the payload', async () => {
    const child = childCategory({ generates_contract: false })
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

    fireEvent.click(pencilOf('generatesContract') as HTMLElement)

    expect(await screen.findByRole('button', { name: 'More info about Includes a contract' })).toBeInTheDocument()
  })

  it('create: a draft under a parent mirrors the root value and offers no pencil', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([
      treeNode({ id: 1, name: 'Electronics', generates_contract: false }),
    ])

    render(
      <ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('button', { name: 'Edit Includes a contract' })).toBeInTheDocument()

    fireEvent.click(pencilOf('parent') as HTMLElement)
    fireEvent.click(await screen.findByRole('combobox', { name: 'Parent category' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Electronics' }))
    applyRow()

    await waitFor(() => expect(pencilOf('generatesContract')).not.toBeInTheDocument())
    expect(screen.getAllByText('Inherited from Electronics').length).toBeGreaterThan(0)
  })
})
