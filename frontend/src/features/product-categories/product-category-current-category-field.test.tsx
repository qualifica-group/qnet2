import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import {
  categoryWrapper,
  category as baseCategory,
  childCategory,
  pencilOf,
  permissionsFor,
  saveRow,
} from '@/features/product-categories/product-category-test-fixtures'
import type { ProductCategoryDetailWithPermissions, ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { ResourceMeta } from '@/features/authorization/types'
import type { ForSelectItem } from '@/features/for-select/types'

/**
 * Spec 0374 AC-022: the "Categoria attuale" row exists only on a legacy root, offers the current roots only
 * (`current_only=1`), and its save sends `current_category_id`. Real `AsyncPaginatedSelect`; only `useForSelect`
 * and the tree fetch are mocked (same approach as `product-category-business-function-field.test.tsx`).
 */

const updateProductCategoryMock = vi.fn()
const fetchProductCategoryTreeMock = vi.fn<() => Promise<ProductCategoryTreeNode[]>>()

vi.mock('@/features/product-categories/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/product-categories/api')>()),
  updateProductCategory: (...args: unknown[]) => updateProductCategoryMock(...args),
  fetchProductCategoryTree: () => fetchProductCategoryTreeMock(),
  fetchEffectiveAttributes: () => Promise.resolve([]),
  fetchEffectiveManagerLabels: () => Promise.resolve({}),
  fetchReportColumnsCatalog: () => Promise.resolve([]),
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

vi.mock('@/features/attributes/use-attribute-catalog', () => ({
  useAttributeCatalog: () => ({ data: [], isPending: false, isError: false, refetch: vi.fn() }),
}))

vi.mock('@/features/product-categories/product-category-attribute-layout-preview', () => ({
  ProductCategoryAttributeLayoutPreview: () => null,
}))

function renderDetail(detail: ProductCategoryDetailWithPermissions) {
  return render(<ProductCategoryDetailView category={detail} />, { wrapper: categoryWrapper().wrapper })
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

const legacyRoot = (overrides: Partial<ProductCategoryDetailWithPermissions> = {}) =>
  baseCategory({ name: '[OLD] ISO', is_legacy: true, current_category_id: null, current_category: null, ...overrides })

const LABEL = 'Current category'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateProductCategoryMock.mockReset()
  fetchProductCategoryTreeMock.mockReset().mockResolvedValue([])
  fetchResourceMetaMock.mockReset().mockResolvedValue({ fields: [], permissions: permissionsFor() })
  useForSelectMock.mockReset().mockReturnValue(queryState())
})

describe('Current category row (spec 0374 AC-022)', () => {
  it('shows on a legacy root, with the saved current root', () => {
    renderDetail(legacyRoot({ current_category_id: 7, current_category: { id: 7, name: 'ISO' } }))

    expect(pencilOf('currentCategory')).toBeInTheDocument()
    expect(screen.getByText('ISO')).toBeInTheDocument()
  })

  it('is absent on a current root and on a subcategory', () => {
    const { unmount } = renderDetail(baseCategory({ is_legacy: false }))
    expect(pencilOf('currentCategory')).not.toBeInTheDocument()
    expect(screen.queryByText(LABEL)).not.toBeInTheDocument()
    unmount()

    renderDetail(childCategory({ is_legacy: false }))
    expect(pencilOf('currentCategory')).not.toBeInTheDocument()
    expect(screen.queryByText(LABEL)).not.toBeInTheDocument()
  })

  it('offers only the current roots and saves the pick as current_category_id', async () => {
    useForSelectMock.mockReturnValue(queryState([{ id: 7, label: 'ISO' }]))
    updateProductCategoryMock.mockResolvedValue(legacyRoot())

    renderDetail(legacyRoot())
    fireEvent.click(pencilOf('currentCategory') as HTMLElement)
    fireEvent.click(await screen.findByRole('combobox', { name: LABEL }))
    fireEvent.click(screen.getByRole('option', { name: 'ISO' }))
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    expect(updateProductCategoryMock.mock.calls[0][1]).toEqual({ current_category_id: 7 })
    expect(useForSelectMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'product-category-roots', params: { current_only: 1 } }),
    )
  })

  it('clearing the link sends null', async () => {
    updateProductCategoryMock.mockResolvedValue(legacyRoot())

    renderDetail(legacyRoot({ current_category_id: 7, current_category: { id: 7, name: 'ISO' } }))
    fireEvent.click(pencilOf('currentCategory') as HTMLElement)
    fireEvent.click(await screen.findByRole('button', { name: 'Clear ISO' }))
    saveRow()

    await waitFor(() => expect(updateProductCategoryMock).toHaveBeenCalledTimes(1))
    expect(updateProductCategoryMock.mock.calls[0][1]).toEqual({ current_category_id: null })
  })
})
