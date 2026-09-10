import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  bulkDeleteTableRows,
  fetchTableColumnValues,
  resetTableFilters,
  saveTableFilters,
  saveTablePreferences,
} from '@/features/table/api'
import { apiClient } from '@/api/client'

vi.mock('@/api/client', () => ({
  apiClient: { post: vi.fn(), delete: vi.fn() },
}))

const postMock = vi.mocked(apiClient.post)
const deleteMock = vi.mocked(apiClient.delete)

beforeEach(() => {
  postMock.mockReset()
  deleteMock.mockReset()
})

describe('fetchTableColumnValues', () => {
  it('posts the payload and unwraps the ok() envelope', async () => {
    postMock.mockResolvedValue({
      data: { success: true, message: 'ok', data: { values: ['a', 'b'], hasMore: false } },
    })

    const result = await fetchTableColumnValues('users', {
      columnId: 'email',
      filterModel: { roles: { filterType: 'set', values: ['admin'] } },
    })

    expect(result).toEqual({ values: ['a', 'b'], hasMore: false })
    expect(postMock).toHaveBeenCalledWith('/tables/users/values', {
      columnId: 'email',
      filterModel: { roles: { filterType: 'set', values: ['admin'] } },
    })
  })
})

describe('saveTableFilters', () => {
  it('posts the filter model wrapped and returns the merged config', async () => {
    const config = { resource: 'users', filtersCustomized: true }
    postMock.mockResolvedValue({
      data: { success: true, message: 'ok', data: config },
    })
    const filterModel = { email: { filterType: 'text', type: 'contains', filter: 'a' } }

    const result = await saveTableFilters('users', { filterModel })

    expect(result).toBe(config)
    expect(postMock).toHaveBeenCalledWith('/tables/users/filters', { filterModel })
  })

  it('carries the category tab so the response keeps its attr.* columns (spec 0064)', async () => {
    postMock.mockResolvedValue({
      data: { success: true, message: 'ok', data: { resource: 'request-management' } },
    })

    await saveTableFilters('request-management', { filterModel: {} }, 12)

    expect(postMock).toHaveBeenCalledWith('/tables/request-management/filters', {
      filterModel: {},
      product_category_id: 12,
    })
  })

  it('posts advanced filters independently of the column filterModel (spec 0032)', async () => {
    const config = { resource: 'users', filtersCustomized: true }
    postMock.mockResolvedValue({
      data: { success: true, message: 'ok', data: config },
    })
    const advancedFilters = { status: 'active' }

    const result = await saveTableFilters('users', { advancedFilters })

    expect(result).toBe(config)
    expect(postMock).toHaveBeenCalledWith('/tables/users/filters', { advancedFilters })
  })
})

describe('saveTablePreferences', () => {
  it('posts only the column state when the table has no category scope', async () => {
    postMock.mockResolvedValue({ data: { success: true, message: 'ok', data: { resource: 'users' } } })

    await saveTablePreferences('users', [{ id: 'email', visible: true, order: 0 }])

    expect(postMock).toHaveBeenCalledWith('/tables/users/preferences', {
      columns: [{ id: 'email', visible: true, order: 0 }],
    })
  })

  it('carries the category tab so the response keeps its attr.* columns (spec 0064)', async () => {
    postMock.mockResolvedValue({
      data: { success: true, message: 'ok', data: { resource: 'request-management' } },
    })

    await saveTablePreferences('request-management', [{ id: 'attr.field_a', visible: true, order: 0 }], 12)

    expect(postMock).toHaveBeenCalledWith('/tables/request-management/preferences', {
      columns: [{ id: 'attr.field_a', visible: true, order: 0 }],
      product_category_id: 12,
    })
  })
})

describe('resetTableFilters', () => {
  it('deletes the saved filters for the domain', async () => {
    deleteMock.mockResolvedValue({ data: null })

    await resetTableFilters('users')

    expect(deleteMock).toHaveBeenCalledWith('/tables/users/filters')
  })
})

describe('bulkDeleteTableRows', () => {
  it('posts the selected ids and unwraps the ok() envelope', async () => {
    const result = {
      deleted: 2,
      failed: [{ id: 3, reason: 'forbidden' as const }],
    }
    postMock.mockResolvedValue({
      data: { success: true, message: 'ok', data: result },
    })

    const response = await bulkDeleteTableRows('users', [1, 2, 3])

    expect(response).toEqual(result)
    expect(postMock).toHaveBeenCalledWith('/tables/users/bulk-delete', {
      ids: [1, 2, 3],
    })
  })
})
