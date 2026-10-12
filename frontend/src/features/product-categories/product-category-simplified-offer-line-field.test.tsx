import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import {
  category,
  categoryWrapper,
  pencilOf,
  permissionsFor,
  saveRow,
  treeNode,
} from '@/features/product-categories/product-category-test-fixtures'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import {
  resolveInheritedSimplifiedOfferLine,
  simplifiedOfferLineOverrideFor,
} from '@/features/product-categories/simplified-offer-line-inheritance'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'
import type { ResourceMeta } from '@/features/authorization/types'

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
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
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

function trainingChild(overrides: Partial<ProductCategoryDetailWithPermissions> = {}) {
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
  saveRow()
  await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
  return updateProductCategoryMock.mock.calls[0][1]
}

describe('simplified_offer_line row (spec 0229)', () => {
  it('AC-013 root: editable, and saving sends the flag, never the override', async () => {
    updateProductCategoryMock.mockResolvedValue(category({ simplified_offer_line: true }))

    renderDetail(category())

    fireEvent.click(pencilOf('simplifiedOfferLine') as HTMLElement)
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

  it('AC-012 child: the row shows the effective value and the inherited badge, and is editable', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue(TRAINING_TREE)

    renderDetail(trainingChild())

    expect(screen.getByText('Inherited from Training')).toBeInTheDocument()
    expect(screen.queryByText('Forced')).not.toBeInTheDocument()
    fireEvent.click(pencilOf('simplifiedOfferLine') as HTMLElement)
    const simplifiedSwitch = await findSimplifiedOfferLineSwitch()
    await waitFor(() => expect(simplifiedSwitch).not.toBeDisabled())
    expect(simplifiedSwitch).toBeChecked()
    expect(within(tileOf(simplifiedSwitch)).getByText('Inherited from Training')).toBeInTheDocument()
  })

  it('AC-012 child: switching off sends override false, switching back on sends null', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue(TRAINING_TREE)
    updateProductCategoryMock.mockResolvedValue(trainingChild())

    const { unmount } = renderDetail(trainingChild())

    fireEvent.click(pencilOf('simplifiedOfferLine') as HTMLElement)
    const simplifiedSwitch = await findSimplifiedOfferLineSwitch()
    await waitFor(() => expect(simplifiedSwitch).not.toBeDisabled())
    fireEvent.click(simplifiedSwitch)

    expect(simplifiedSwitch).not.toBeChecked()
    expect(within(tileOf(simplifiedSwitch)).getByText('Forced')).toBeInTheDocument()
    expect(await saveEdit()).toEqual({ simplified_offer_line_override: false })
    unmount()
    updateProductCategoryMock.mockClear()

    renderDetail(
      trainingChild({
        simplified_offer_line: false,
        simplified_offer_line_override: false,
        simplified_offer_line_source_category: null,
      }),
    )

    fireEvent.click(pencilOf('simplifiedOfferLine') as HTMLElement)
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
    renderDetail(category())

    fireEvent.click(pencilOf('simplifiedOfferLine') as HTMLElement)

    expect(
      await screen.findByRole('button', { name: 'More info about Simplified offer line' }),
    ).toBeInTheDocument()
  })
})
