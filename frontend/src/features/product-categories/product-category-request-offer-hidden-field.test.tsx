import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import {
  category,
  categoryWrapper,
  childCategory,
  pencilOf,
  permissionsFor,
  saveRow,
  treeNode,
} from '@/features/product-categories/product-category-test-fixtures'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import { resolveInheritedRequestOfferHiddenFlag } from '@/features/product-categories/request-offer-hidden-inheritance'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * `request_offer_hidden` (spec 0414) belongs to the branch ROOT, like
 * `multiple_open_opportunities`: a root edits it, a child mirrors the root.
 * Same setup as the multiple-open-opportunities suite.
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

const NO_EFFECT = /the branch is not used in Gestione Richieste/

async function findOfferHiddenSwitch() {
  return screen.findByRole('switch', { name: 'Hide the offer in Gestione Richieste' })
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

describe('resolveInheritedRequestOfferHiddenFlag', () => {
  it('resolves the ROOT flag for a child pick and null for a root pick', () => {
    const nodesById = indexCategoryTree([
      treeNode({
        id: 1,
        name: 'Electronics',
        request_offer_hidden: true,
        children: [treeNode({ id: 2, name: 'Wiring', parent_id: 1, request_offer_hidden: true })],
      }),
    ])

    expect(resolveInheritedRequestOfferHiddenFlag(nodesById, 2)).toEqual({
      requestOfferHidden: true,
      sourceCategory: { id: 1, name: 'Electronics' },
    })
    expect(resolveInheritedRequestOfferHiddenFlag(nodesById, null)).toBeNull()
  })
})

describe('request_offer_hidden row (spec 0414 AC-004)', () => {
  it('root category: toggling sends the flag on save', async () => {
    updateProductCategoryMock.mockResolvedValue(category({ request_offer_hidden: true }))
    renderDetail(category({ request_offer_hidden: false }))

    fireEvent.click(pencilOf('requestOfferHidden') as HTMLElement)
    const control = await findOfferHiddenSwitch()
    expect(control).not.toBeDisabled()
    fireEvent.click(control)
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ request_offer_hidden: true })
  })

  it('notes, without locking, that a branch not used in Gestione Richieste is unaffected', async () => {
    renderDetail(category({ usage_area: 'opportunities' }))

    fireEvent.click(pencilOf('requestOfferHidden') as HTMLElement)

    expect(await findOfferHiddenSwitch()).not.toBeDisabled()
    expect(screen.getByText(NO_EFFECT)).toBeInTheDocument()
  })

  it('child category: mirrors the root, names it and offers no pencil', () => {
    renderDetail(childCategory({ request_offer_hidden: true }))

    expect(pencilOf('requestOfferHidden')).not.toBeInTheDocument()
    expect(screen.getAllByText('Inherited from Electronics').length).toBeGreaterThan(0)
  })
})
