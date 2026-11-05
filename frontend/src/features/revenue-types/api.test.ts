import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import { fetchRevenueTypesForSelect } from '@/features/revenue-types/api'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))

const getMock = vi.mocked(apiClient.get)
const DATA = { items: [], total: 0, offset: 0 }

beforeEach(() => getMock.mockReset())

describe('fetchRevenueTypesForSelect', () => {
  it('sends search, ids[] and paging and unwraps the envelope', async () => {
    getMock.mockResolvedValue({ data: { success: true, message: '', data: DATA } })

    const result = await fetchRevenueTypesForSelect({ search: '70', ids: [3, 4] })

    expect(result).toEqual(DATA)
    expect(getMock).toHaveBeenCalledWith(
      '/revenue-types/for-select',
      expect.objectContaining({ params: { offset: 0, limit: 100, search: '70', ids: [3, 4] }, paramsSerializer: { indexes: true } }),
    )
  })

  it('omits empty search and ids and accepts an unwrapped payload', async () => {
    getMock.mockResolvedValue({ data: DATA })

    const result = await fetchRevenueTypesForSelect({ search: '', ids: [] })

    expect(result).toEqual(DATA)
    expect(getMock).toHaveBeenCalledWith('/revenue-types/for-select', expect.objectContaining({ params: { offset: 0, limit: 100 } }))
  })
})
