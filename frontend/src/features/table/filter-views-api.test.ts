import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  createFilterView,
  deleteFilterView,
  favoriteFilterView,
  listFilterViews,
  unfavoriteFilterView,
  updateFilterView,
} from '@/features/table/filter-views-api'
import { apiClient } from '@/api/client'
import type { FilterViewInput, TableFilterView } from '@/features/table/types'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const getMock = vi.mocked(apiClient.get)
const postMock = vi.mocked(apiClient.post)
const putMock = vi.mocked(apiClient.put)
const deleteMock = vi.mocked(apiClient.delete)

beforeEach(() => {
  getMock.mockReset()
  postMock.mockReset()
  putMock.mockReset()
  deleteMock.mockReset()
})

const VIEW: TableFilterView = {
  id: 12,
  name: 'Active admins',
  filters: { roles: { filterType: 'set', values: ['admin'] } },
  advanced_filters: {},
  visibility: 'shared',
  owned: true,
  owner_name: null,
  rules: null,
  is_favorite: false,
}

describe('listFilterViews', () => {
  it('gets the domain views and unwraps the ok() envelope', async () => {
    getMock.mockResolvedValue({
      data: { success: true, message: 'ok', data: [VIEW] },
    })

    const result = await listFilterViews('users')

    expect(result).toEqual([VIEW])
    expect(getMock).toHaveBeenCalledWith('/tables/users/filter-views')
  })
})

describe('createFilterView', () => {
  it('posts the input and returns the created view', async () => {
    postMock.mockResolvedValue({
      data: { success: true, message: 'ok', data: VIEW },
    })
    const input: FilterViewInput = {
      name: 'Active admins',
      filters: VIEW.filters,
      visibility: 'shared',
    }

    const result = await createFilterView('users', input)

    expect(result).toBe(VIEW)
    expect(postMock).toHaveBeenCalledWith('/tables/users/filter-views', input)
  })
})

describe('updateFilterView', () => {
  it('puts the input at the view id and returns the updated view', async () => {
    putMock.mockResolvedValue({
      data: { success: true, message: 'ok', data: VIEW },
    })
    const input: FilterViewInput = {
      name: 'Active admins',
      filters: VIEW.filters,
      visibility: 'private',
    }

    const result = await updateFilterView('users', 12, input)

    expect(result).toBe(VIEW)
    expect(putMock).toHaveBeenCalledWith('/tables/users/filter-views/12', input)
  })
})

describe('deleteFilterView', () => {
  it('deletes the view by id', async () => {
    deleteMock.mockResolvedValue({ data: null })

    await deleteFilterView('users', 12)

    expect(deleteMock).toHaveBeenCalledWith('/tables/users/filter-views/12')
  })
})

describe('favoriteFilterView', () => {
  it('posts to the favorite sub-path and returns the updated view', async () => {
    const favorited = { ...VIEW, is_favorite: true }
    postMock.mockResolvedValue({ data: { success: true, message: 'ok', data: favorited } })

    const result = await favoriteFilterView('users', 12)

    expect(result).toEqual(favorited)
    expect(postMock).toHaveBeenCalledWith('/tables/users/filter-views/12/favorite')
  })
})

describe('unfavoriteFilterView', () => {
  it('deletes the favorite sub-path and returns the updated view', async () => {
    deleteMock.mockResolvedValue({ data: { success: true, message: 'ok', data: VIEW } })

    const result = await unfavoriteFilterView('users', 12)

    expect(result).toEqual(VIEW)
    expect(deleteMock).toHaveBeenCalledWith('/tables/users/filter-views/12/favorite')
  })
})
