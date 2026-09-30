import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { useCategoryTabPreferences } from '@/features/request-management/use-category-tab-preferences'
import { fetchCategoryTabPreferences, saveCategoryTabPreferences } from '@/features/request-management/api'

vi.mock('@/features/request-management/api', () => ({
  fetchCategoryTabPreferences: vi.fn(),
  saveCategoryTabPreferences: vi.fn(),
}))
vi.mock('sonner', () => ({ toast: { error: vi.fn() } }))

const fetchMock = vi.mocked(fetchCategoryTabPreferences)
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
  fetchMock.mockReset()
  saveMock.mockReset()
  vi.mocked(toast.error).mockReset()
})

describe('useCategoryTabPreferences (spec 0184)', () => {
  it('starts with no favorites before anything loads', () => {
    fetchMock.mockReturnValue(new Promise(() => {}))
    const { result } = renderHook(() => useCategoryTabPreferences(), { wrapper: wrapper() })

    expect(result.current.preferences).toEqual({ favorite_category_ids: [], show_only_favorites: false })
  })

  it('adds and removes a favorite optimistically, sending the whole preference (AC-010)', async () => {
    fetchMock.mockResolvedValue({ favorite_category_ids: [3], show_only_favorites: true })
    saveMock.mockImplementation(async (_basePath, next) => next)
    const { result } = renderHook(() => useCategoryTabPreferences(), { wrapper: wrapper() })
    await waitFor(() => expect(result.current.preferences.favorite_category_ids).toEqual([3]))

    act(() => result.current.toggleFavorite(7))
    await waitFor(() => expect(result.current.preferences.favorite_category_ids).toEqual([3, 7]))
    expect(saveMock).toHaveBeenLastCalledWith('/request-management', {
      favorite_category_ids: [3, 7],
      show_only_favorites: true,
    })

    act(() => result.current.toggleFavorite(3))
    await waitFor(() => expect(result.current.preferences.favorite_category_ids).toEqual([7]))
  })

  it('restores the previous state and warns when the save fails (AC-010)', async () => {
    fetchMock.mockResolvedValue({ favorite_category_ids: [3], show_only_favorites: false })
    saveMock.mockRejectedValue(new Error('network'))
    const { result } = renderHook(() => useCategoryTabPreferences(), { wrapper: wrapper() })
    await waitFor(() => expect(result.current.preferences.favorite_category_ids).toEqual([3]))

    act(() => result.current.setShowOnlyFavorites(true))

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith('Could not save your favorite categories.'))
    expect(result.current.preferences).toEqual({ favorite_category_ids: [3], show_only_favorites: false })
  })
})
