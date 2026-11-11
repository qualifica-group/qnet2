import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
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
import { resolveInheritedMultipleOpenOpportunitiesFlag } from '@/features/product-categories/multiple-open-opportunities-inheritance'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * `multiple_open_opportunities` (user request 2026-11-11) belongs to the branch ROOT, like
 * `multi_quote_work_order`; turned off on a branch worked in Gestione Richieste it warns that
 * a returning client is refused there. Also covers the companion lock of the simplified
 * offer line on a branch not used in Gestione Richieste. Same setup as the multi-offer suite.
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

const OFF_WARNING = /a new request for a client with an open opportunity is refused/
const NO_EFFECT = /the branch is not used in Gestione Richieste/

async function findMultipleOpenSwitch() {
  return screen.findByRole('switch', { name: 'Several open opportunities per registry' })
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

describe('resolveInheritedMultipleOpenOpportunitiesFlag', () => {
  it('resolves the ROOT flag for a child pick and null for a root pick', () => {
    const nodesById = indexCategoryTree([
      treeNode({
        id: 1,
        name: 'Electronics',
        multiple_open_opportunities: true,
        children: [treeNode({ id: 2, name: 'Wiring', parent_id: 1, multiple_open_opportunities: true })],
      }),
    ])

    expect(resolveInheritedMultipleOpenOpportunitiesFlag(nodesById, 2)).toEqual({
      multipleOpenOpportunities: true,
      sourceCategory: { id: 1, name: 'Electronics' },
    })
    expect(resolveInheritedMultipleOpenOpportunitiesFlag(nodesById, null)).toBeNull()
  })
})

describe('multiple_open_opportunities row', () => {
  it('root category: toggling sends the flag on save', async () => {
    updateProductCategoryMock.mockResolvedValue(category({ multiple_open_opportunities: true }))
    renderDetail(category({ multiple_open_opportunities: false }))

    fireEvent.click(pencilOf('multipleOpenOpportunities') as HTMLElement)
    const control = await findMultipleOpenSwitch()
    expect(control).not.toBeDisabled()
    fireEvent.click(control)
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateProductCategoryMock.mock.calls[0]
    expect(payload).toEqual({ multiple_open_opportunities: true })
  })

  it('warns, without locking, when a single-mode branch has the rule off', async () => {
    renderDetail(category({ management_mode: 'single', multiple_open_opportunities: false }))

    fireEvent.click(pencilOf('multipleOpenOpportunities') as HTMLElement)

    expect(await findMultipleOpenSwitch()).not.toBeDisabled()
    expect(screen.getByRole('note')).toHaveTextContent(OFF_WARNING)
  })

  it('warns when a requests-only branch has the rule off', async () => {
    renderDetail(category({ usage_area: 'requests', multiple_open_opportunities: false }))

    fireEvent.click(pencilOf('multipleOpenOpportunities') as HTMLElement)
    await findMultipleOpenSwitch()

    expect(screen.getByText(OFF_WARNING)).toBeInTheDocument()
  })

  it('does not warn on a multiple-mode branch used everywhere, nor once the rule is on', async () => {
    renderDetail(category({ management_mode: 'multiple', usage_area: 'all', multiple_open_opportunities: false }))
    fireEvent.click(pencilOf('multipleOpenOpportunities') as HTMLElement)
    await findMultipleOpenSwitch()
    expect(screen.queryByText(OFF_WARNING)).not.toBeInTheDocument()

    cleanup()
    renderDetail(category({ management_mode: 'single', multiple_open_opportunities: true }))
    fireEvent.click(pencilOf('multipleOpenOpportunities') as HTMLElement)
    await findMultipleOpenSwitch()
    expect(screen.queryByText(OFF_WARNING)).not.toBeInTheDocument()
  })

  it('child category: mirrors the root, names it and offers no pencil', () => {
    renderDetail(childCategory({ multiple_open_opportunities: true }))

    expect(pencilOf('multipleOpenOpportunities')).not.toBeInTheDocument()
    expect(screen.getAllByText('Inherited from Electronics').length).toBeGreaterThan(0)
  })
})

describe('simplified offer line on a branch not used in Gestione Richieste', () => {
  it('locks the switch off and says why', async () => {
    renderDetail(category({ usage_area: 'opportunities', simplified_offer_line: false }))

    fireEvent.click(pencilOf('simplifiedOfferLine') as HTMLElement)

    expect(await screen.findByRole('switch', { name: 'Simplified offer line' })).toBeDisabled()
    expect(screen.getByText(NO_EFFECT)).toBeInTheDocument()
  })

  it('still lets a rule already on be turned off', async () => {
    renderDetail(category({ usage_area: 'opportunities', simplified_offer_line: true }))

    fireEvent.click(pencilOf('simplifiedOfferLine') as HTMLElement)

    expect(await screen.findByRole('switch', { name: 'Simplified offer line' })).not.toBeDisabled()
  })

  it('stays free on a branch used in Gestione Richieste', async () => {
    renderDetail(category({ usage_area: 'all', simplified_offer_line: false }))

    fireEvent.click(pencilOf('simplifiedOfferLine') as HTMLElement)

    expect(await screen.findByRole('switch', { name: 'Simplified offer line' })).not.toBeDisabled()
    expect(screen.queryByText(NO_EFFECT)).not.toBeInTheDocument()
  })
})
