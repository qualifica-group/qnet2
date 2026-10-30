import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { useCategoryTabStrip } from '@/features/category-tabs/use-category-tab-strip'
import {
  fetchCategoryTabPreferences,
  fetchGridCategoryTabs,
  saveCategoryTabPreferences,
} from '@/features/category-tabs/api'

vi.mock('@/features/category-tabs/api', () => ({
  fetchGridCategoryTabs: vi.fn(),
  fetchCategoryTabPreferences: vi.fn(),
  saveCategoryTabPreferences: vi.fn(),
}))
vi.mock('sonner', () => ({ toast: { error: vi.fn() } }))

/**
 * Spec 0337 AC-008 / AC-009: the strip level (all | root), its persistence, the
 * selection each level remembers, and the favourites kept apart per level.
 */
const SOURCE = { key: 'quotes', apiBasePath: '/quotes' }
const ALL_CATEGORIES = [
  { id: 12, name: 'ISO 9001', count: 5 },
  { id: 7, name: 'Formazione', count: 2 },
]
const ROOT_CATEGORIES = [{ id: 3, name: 'Qualita', count: 9 }]
const ALL_FAVORITES = { favorite_category_ids: [7], show_only_favorites: false, is_default: false }
const ROOT_FAVORITES = { favorite_category_ids: [3], show_only_favorites: false, is_default: false }

const categoriesMock = vi.mocked(fetchGridCategoryTabs)
const fetchPreferencesMock = vi.mocked(fetchCategoryTabPreferences)
const saveMock = vi.mocked(saveCategoryTabPreferences)

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeEach(() => {
  window.localStorage.clear()
  categoriesMock
    .mockReset()
    .mockImplementation(async (_basePath, level) => (level === 'root' ? ROOT_CATEGORIES : ALL_CATEGORIES))
  fetchPreferencesMock
    .mockReset()
    .mockImplementation(async (_basePath, level) => (level === 'root' ? ROOT_FAVORITES : ALL_FAVORITES))
  saveMock.mockReset().mockImplementation(async (_basePath, next) => ({ ...next, is_default: false }))
})

afterEach(() => {
  window.localStorage.clear()
})

describe('useCategoryTabStrip level (spec 0337 AC-008)', () => {
  it('defaults to the all level: today\'s call, today\'s scope', async () => {
    window.localStorage.setItem('quotes.category-tab', '12')
    const { result } = renderHook(() => useCategoryTabStrip(SOURCE), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.scope).toEqual({ productCategoryId: 12 }))
    expect(result.current.tabsProps.level).toBe('all')
    expect(result.current.tableKey).toBe(12)
    expect(categoriesMock).toHaveBeenCalledWith('/quotes')
  })

  it('switching to root reads the root categories, persists the level and scopes the grid by branch', async () => {
    window.localStorage.setItem('quotes.category-tab.root', '3')
    const { result } = renderHook(() => useCategoryTabStrip(SOURCE), { wrapper: wrapper() })
    await waitFor(() => expect(result.current.isPending).toBe(false))

    act(() => result.current.tabsProps.onLevelChange('root'))

    await waitFor(() => expect(result.current.tabsProps.categories).toEqual(ROOT_CATEGORIES))
    expect(categoriesMock).toHaveBeenCalledWith('/quotes', 'root')
    expect(window.localStorage.getItem('quotes.category-tab-level')).toBe('root')
    expect(result.current.scope).toEqual({ productCategoryBranchId: 3 })
    expect(result.current.tableKey).toBe('root-3')
    // A branch has no single category: the category-specific consumers get null.
    expect(result.current.selectedCategoryId).toBeNull()
  })

  it('restores the persisted level on mount', async () => {
    window.localStorage.setItem('quotes.category-tab-level', 'root')
    window.localStorage.setItem('quotes.category-tab.root', '3')
    const { result } = renderHook(() => useCategoryTabStrip(SOURCE), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.scope).toEqual({ productCategoryBranchId: 3 }))
    expect(categoriesMock).toHaveBeenCalledWith('/quotes', 'root')
  })

  it('each level remembers its own tab; going back to all restores the earlier one', async () => {
    const { result } = renderHook(() => useCategoryTabStrip(SOURCE), { wrapper: wrapper() })
    await waitFor(() => expect(result.current.isPending).toBe(false))
    act(() => result.current.tabsProps.onSelect(12))

    act(() => result.current.tabsProps.onLevelChange('root'))
    await waitFor(() => expect(result.current.tabsProps.categories).toEqual(ROOT_CATEGORIES))
    expect(result.current.scope).toBeUndefined()
    act(() => result.current.tabsProps.onSelect(3))
    expect(window.localStorage.getItem('quotes.category-tab.root')).toBe('3')

    act(() => result.current.tabsProps.onLevelChange('all'))
    await waitFor(() => expect(result.current.scope).toEqual({ productCategoryId: 12 }))
    expect(window.localStorage.getItem('quotes.category-tab')).toBe('12')
  })

  it('falls back to "Tutte" when the stored root tab is not in the root bar', async () => {
    window.localStorage.setItem('quotes.category-tab-level', 'root')
    window.localStorage.setItem('quotes.category-tab.root', '12')
    const { result } = renderHook(() => useCategoryTabStrip(SOURCE), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.isPending).toBe(false))
    expect(result.current.scope).toBeUndefined()
    expect(result.current.tableKey).toBe('all')
  })

  it('re-reads both levels when invalidated', async () => {
    const { result } = renderHook(() => useCategoryTabStrip(SOURCE), { wrapper: wrapper() })
    await waitFor(() => expect(result.current.isPending).toBe(false))
    act(() => result.current.tabsProps.onLevelChange('root'))
    await waitFor(() => expect(categoriesMock).toHaveBeenCalledWith('/quotes', 'root'))
    categoriesMock.mockClear()

    act(() => result.current.invalidateCategories())

    await waitFor(() => expect(categoriesMock).toHaveBeenCalledWith('/quotes', 'root'))
  })
})

describe('useCategoryTabStrip favorites per level (spec 0337 AC-009)', () => {
  it('reads and writes the root favorites with level=root, leaving the all level untouched', async () => {
    window.localStorage.setItem('quotes.category-tab-level', 'root')
    const { result } = renderHook(() => useCategoryTabStrip(SOURCE), { wrapper: wrapper() })
    await waitFor(() => expect(result.current.tabsProps.favoriteCategoryIds).toEqual([3]))
    expect(fetchPreferencesMock).toHaveBeenCalledWith('/quotes', 'root')

    act(() => result.current.tabsProps.onShowOnlyFavoritesChange(true))

    await waitFor(() => expect(result.current.tabsProps.showOnlyFavorites).toBe(true))
    expect(saveMock).toHaveBeenCalledWith('/quotes', { favorite_category_ids: [3], show_only_favorites: true }, 'root')

    act(() => result.current.tabsProps.onLevelChange('all'))
    await waitFor(() => expect(result.current.tabsProps.favoriteCategoryIds).toEqual([7]))
    expect(result.current.tabsProps.showOnlyFavorites).toBe(false)
  })
})
