import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { toast } from 'sonner'
import i18n from '@/i18n'
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

const SOURCE = { key: 'quotes', apiBasePath: '/quotes' }
const CATEGORIES = [
  { id: 12, name: 'ISO 9001', count: 5 },
  { id: 7, name: 'Formazione', count: 2 },
]
const SAVED = { favorite_category_ids: [7], show_only_favorites: false, is_default: false }

const categoriesMock = vi.mocked(fetchGridCategoryTabs)
const fetchPreferencesMock = vi.mocked(fetchCategoryTabPreferences)
const saveMock = vi.mocked(saveCategoryTabPreferences)

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  window.localStorage.clear()
  categoriesMock.mockReset().mockResolvedValue(CATEGORIES)
  fetchPreferencesMock.mockReset().mockResolvedValue(SAVED)
  saveMock.mockReset()
  vi.mocked(toast.error).mockReset()
})

afterEach(() => {
  window.localStorage.clear()
})

describe('useCategoryTabStrip selection (spec 0335 AC-014/015)', () => {
  it('selects the persisted category once it is confirmed in the live list', async () => {
    window.localStorage.setItem('quotes.category-tab', '12')
    const { result } = renderHook(() => useCategoryTabStrip(SOURCE), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.selectedCategoryId).toBe(12))
    expect(categoriesMock).toHaveBeenCalledWith('/quotes')
  })

  it('falls back to "Tutte" when the persisted category is no longer listed', async () => {
    window.localStorage.setItem('quotes.category-tab', '999')
    const { result } = renderHook(() => useCategoryTabStrip(SOURCE), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.isPending).toBe(false))
    expect(result.current.selectedCategoryId).toBeNull()
  })

  it('persists a newly picked category', async () => {
    const { result } = renderHook(() => useCategoryTabStrip(SOURCE), { wrapper: wrapper() })
    await waitFor(() => expect(result.current.isPending).toBe(false))

    act(() => result.current.tabsProps.onSelect(12))

    expect(result.current.selectedCategoryId).toBe(12)
    expect(window.localStorage.getItem('quotes.category-tab')).toBe('12')
  })

  it('uses the injected fetcher instead of the grid endpoint', async () => {
    const custom = vi.fn().mockResolvedValue([{ id: 1, name: 'Custom', count: 1 }])
    const { result } = renderHook(() => useCategoryTabStrip(SOURCE, custom), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.tabsProps.categories).toHaveLength(1))
    expect(custom).toHaveBeenCalledWith('/quotes')
    expect(categoriesMock).not.toHaveBeenCalled()
  })

  it('re-reads the categories when invalidated (AC-017)', async () => {
    const { result } = renderHook(() => useCategoryTabStrip(SOURCE), { wrapper: wrapper() })
    await waitFor(() => expect(result.current.isPending).toBe(false))

    act(() => result.current.invalidateCategories())

    await waitFor(() => expect(categoriesMock).toHaveBeenCalledTimes(2))
  })
})

describe('useCategoryTabStrip favorites (spec 0335 AC-016)', () => {
  it('toggles a favorite optimistically with a PUT to the grid endpoint', async () => {
    saveMock.mockImplementation(async (_basePath, next) => ({ ...next, is_default: false }))
    const { result } = renderHook(() => useCategoryTabStrip(SOURCE), { wrapper: wrapper() })
    await waitFor(() => expect(result.current.tabsProps.favoriteCategoryIds).toEqual([7]))

    act(() => result.current.tabsProps.onToggleFavorite(12))

    await waitFor(() => expect(result.current.tabsProps.favoriteCategoryIds).toEqual([7, 12]))
    expect(saveMock).toHaveBeenCalledWith('/quotes', { favorite_category_ids: [7, 12], show_only_favorites: false })
  })

  it('sends "only favorites" with the current favorites', async () => {
    saveMock.mockImplementation(async (_basePath, next) => ({ ...next, is_default: false }))
    const { result } = renderHook(() => useCategoryTabStrip(SOURCE), { wrapper: wrapper() })
    await waitFor(() => expect(result.current.tabsProps.favoriteCategoryIds).toEqual([7]))

    act(() => result.current.tabsProps.onShowOnlyFavoritesChange(true))

    await waitFor(() => expect(result.current.tabsProps.showOnlyFavorites).toBe(true))
    expect(saveMock).toHaveBeenCalledWith('/quotes', { favorite_category_ids: [7], show_only_favorites: true })
  })

  it('rolls back and shows the categoryTabs.favoritesError toast when the save fails', async () => {
    saveMock.mockRejectedValue(new Error('network'))
    const { result } = renderHook(() => useCategoryTabStrip(SOURCE), { wrapper: wrapper() })
    await waitFor(() => expect(result.current.tabsProps.favoriteCategoryIds).toEqual([7]))

    act(() => result.current.tabsProps.onToggleFavorite(12))

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith('Could not save your favorite categories.'))
    expect(result.current.tabsProps.favoriteCategoryIds).toEqual([7])
  })
})
