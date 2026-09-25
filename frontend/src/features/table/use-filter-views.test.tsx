import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import {
  filterViewKeys,
  useCreateFilterView,
  useDeleteFilterView,
  useFilterViews,
  useToggleFilterViewFavorite,
} from '@/features/table/use-filter-views'
import type { FilterViewInput, TableFilterView } from '@/features/table/types'

const listFilterViewsMock = vi.fn()
const createFilterViewMock = vi.fn()
const updateFilterViewMock = vi.fn()
const deleteFilterViewMock = vi.fn()
const favoriteFilterViewMock = vi.fn()
const unfavoriteFilterViewMock = vi.fn()

vi.mock('@/features/table/filter-views-api', () => ({
  listFilterViews: (...args: unknown[]) => listFilterViewsMock(...args),
  createFilterView: (...args: unknown[]) => createFilterViewMock(...args),
  updateFilterView: (...args: unknown[]) => updateFilterViewMock(...args),
  deleteFilterView: (...args: unknown[]) => deleteFilterViewMock(...args),
  favoriteFilterView: (...args: unknown[]) => favoriteFilterViewMock(...args),
  unfavoriteFilterView: (...args: unknown[]) => unfavoriteFilterViewMock(...args),
}))

const VIEW: TableFilterView = {
  id: 1,
  name: 'Active admins',
  filters: { roles: { filterType: 'set', values: ['admin'] } },
  advanced_filters: {},
  visibility: 'shared',
  owned: true,
  owner_name: null,
  rules: null,
  is_favorite: false,
}

function wrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })
  const Wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  return { client, Wrapper }
}

beforeEach(() => {
  listFilterViewsMock.mockReset()
  createFilterViewMock.mockReset()
  updateFilterViewMock.mockReset()
  deleteFilterViewMock.mockReset()
  favoriteFilterViewMock.mockReset()
  unfavoriteFilterViewMock.mockReset()
})

describe('filterViewKeys', () => {
  it('is namespaced by domain', () => {
    expect(filterViewKeys.list('users')).toEqual(['table', 'users', 'filter-views'])
  })
})

describe('useFilterViews', () => {
  it('loads the domain views', async () => {
    listFilterViewsMock.mockResolvedValue([VIEW])
    const { Wrapper } = wrapper()

    const { result } = renderHook(() => useFilterViews('users'), { wrapper: Wrapper })

    await waitFor(() => expect(result.current.isSuccess).toBe(true))
    expect(result.current.data).toEqual([VIEW])
    expect(listFilterViewsMock).toHaveBeenCalledWith('users')
  })
})

describe('useCreateFilterView', () => {
  it('invalidates the list query on success', async () => {
    createFilterViewMock.mockResolvedValue(VIEW)
    listFilterViewsMock.mockResolvedValue([VIEW])
    const { client, Wrapper } = wrapper()
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    const { result } = renderHook(() => useCreateFilterView('users'), { wrapper: Wrapper })

    const input: FilterViewInput = {
      name: 'Active admins',
      filters: VIEW.filters,
      visibility: 'shared',
    }
    await result.current.mutateAsync(input)

    expect(createFilterViewMock).toHaveBeenCalledWith('users', input)
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: filterViewKeys.list('users') })
  })
})

describe('useDeleteFilterView', () => {
  it('invalidates the list query on success', async () => {
    deleteFilterViewMock.mockResolvedValue(undefined)
    const { client, Wrapper } = wrapper()
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    const { result } = renderHook(() => useDeleteFilterView('users'), { wrapper: Wrapper })

    await result.current.mutateAsync(1)

    expect(deleteFilterViewMock).toHaveBeenCalledWith('users', 1)
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: filterViewKeys.list('users') })
  })
})

describe('useToggleFilterViewFavorite', () => {
  it('favorites when not already favorite, and invalidates the list', async () => {
    favoriteFilterViewMock.mockResolvedValue({ ...VIEW, is_favorite: true })
    const { client, Wrapper } = wrapper()
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    const { result } = renderHook(() => useToggleFilterViewFavorite('users'), { wrapper: Wrapper })

    await result.current.mutateAsync({ id: 1, isFavorite: false })

    expect(favoriteFilterViewMock).toHaveBeenCalledWith('users', 1)
    expect(unfavoriteFilterViewMock).not.toHaveBeenCalled()
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: filterViewKeys.list('users') })
  })

  it('unfavorites when already favorite', async () => {
    unfavoriteFilterViewMock.mockResolvedValue({ ...VIEW, is_favorite: false })
    const { Wrapper } = wrapper()

    const { result } = renderHook(() => useToggleFilterViewFavorite('users'), { wrapper: Wrapper })

    await result.current.mutateAsync({ id: 1, isFavorite: true })

    expect(unfavoriteFilterViewMock).toHaveBeenCalledWith('users', 1)
    expect(favoriteFilterViewMock).not.toHaveBeenCalled()
  })
})
