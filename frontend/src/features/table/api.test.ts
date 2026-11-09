import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  bulkDeleteTableRows,
  fetchTableColumnValues,
  fetchTableConfig,
  resetTableFilters,
  saveTableFilters,
  saveTablePreferences,
} from '@/features/table/api'
import { apiClient } from '@/api/client'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}))

const postMock = vi.mocked(apiClient.post)
const deleteMock = vi.mocked(apiClient.delete)

beforeEach(() => {
  postMock.mockReset()
  deleteMock.mockReset()
})

describe('fetchTableConfig', () => {
  it('asks a parent tab config by branch and a single category by id', async () => {
    const getMock = vi.mocked(apiClient.get)
    getMock.mockReset().mockResolvedValue({ data: { success: true, message: 'ok', data: { resource: 'quotes' } } })

    await fetchTableConfig('quotes', { branchId: 3 })
    await fetchTableConfig('quotes', 12)

    expect(getMock).toHaveBeenNthCalledWith(1, '/tables/quotes/columns', { params: { product_category_branch_id: 3 } })
    expect(getMock).toHaveBeenNthCalledWith(2, '/tables/quotes/columns', { params: { product_category_id: 12 } })
  })
})

describe('attribute columns from filters (spec 0374, AC-015)', () => {
  const FROM_FILTERS = { attributeColumnsFromFilters: true } as const

  it('sends the flag as the number 1 on the config GET, nothing without it', async () => {
    const getMock = vi.mocked(apiClient.get)
    getMock.mockReset().mockResolvedValue({ data: { success: true, message: 'ok', data: { resource: 'quotes' } } })

    await fetchTableConfig('quotes', FROM_FILTERS)
    await fetchTableConfig('quotes')

    expect(getMock).toHaveBeenNthCalledWith(1, '/tables/quotes/columns', { params: { attribute_columns_from_filters: 1 } })
    expect(getMock).toHaveBeenNthCalledWith(2, '/tables/quotes/columns', undefined)
  })

  it('sends it on preferences and filters saves, keepalive ones included', async () => {
    postMock.mockResolvedValue({ data: { success: true, message: 'ok', data: { resource: 'quotes' } } })

    await saveTablePreferences('quotes', [], FROM_FILTERS)
    await saveTableFilters('quotes', { filterModel: {} }, FROM_FILTERS, { keepalive: true })

    expect(postMock).toHaveBeenNthCalledWith(1, '/tables/quotes/preferences', { columns: [], attribute_columns_from_filters: 1 })
    expect(postMock).toHaveBeenNthCalledWith(
      2,
      '/tables/quotes/filters',
      { filterModel: {}, attribute_columns_from_filters: 1 },
      expect.objectContaining({ fetchOptions: { keepalive: true } }),
    )
  })
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

  it('carries a parent tab so the response keeps its attr.* columns', async () => {
    postMock.mockResolvedValue({
      data: { success: true, message: 'ok', data: { resource: 'quotes' } },
    })

    await saveTableFilters('quotes', { filterModel: {} }, { branchId: 3 })

    expect(postMock).toHaveBeenCalledWith('/tables/quotes/filters', {
      filterModel: {},
      product_category_branch_id: 3,
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

  // A save sent on page unload must outlive the page: an ordinary XHR is cancelled.
  it('sends through the fetch adapter with keepalive when asked to survive an unload', async () => {
    postMock.mockResolvedValue({ data: { success: true, message: 'ok', data: { resource: 'users' } } })

    await saveTablePreferences('users', [{ id: 'email', visible: true, order: 0 }], undefined, { keepalive: true })

    expect(postMock).toHaveBeenCalledWith(
      '/tables/users/preferences',
      { columns: [{ id: 'email', visible: true, order: 0 }] },
      { adapter: 'fetch', fetchOptions: { keepalive: true } },
    )
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
