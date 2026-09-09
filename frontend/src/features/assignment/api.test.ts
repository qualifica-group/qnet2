import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchRequiredCategories } from '@/features/assignment/api'
import { apiClient } from '@/api/client'

vi.mock('@/api/client', () => ({
  apiClient: { post: vi.fn() },
}))

const postMock = vi.mocked(apiClient.post)

/** Standard `{ success, message, data }` envelope as seen by axios. */
function envelope(productCategoryIds: number[]) {
  return {
    data: {
      success: true,
      message: 'ok',
      data: { product_category_ids: productCategoryIds },
    },
  }
}

beforeEach(() => {
  postMock.mockReset()
})

describe('fetchRequiredCategories', () => {
  it('posts an SSRM import-rows selection verbatim and unwraps the union', async () => {
    postMock.mockResolvedValue(envelope([3, 7]))

    const result = await fetchRequiredCategories({
      domain: 'import_rows',
      import_run_id: 12,
      select_all: true,
      row_ids: [4, 5],
    })

    expect(result).toEqual([3, 7])
    expect(postMock).toHaveBeenCalledWith('/assignment/required-categories', {
      domain: 'import_rows',
      import_run_id: 12,
      select_all: true,
      row_ids: [4, 5],
    })
  })

  it('posts an id-based selection for the leads and quotes domains', async () => {
    postMock.mockResolvedValue(envelope([1]))

    await fetchRequiredCategories({ domain: 'leads', ids: [8, 9] })
    expect(postMock).toHaveBeenCalledWith('/assignment/required-categories', {
      domain: 'leads',
      ids: [8, 9],
    })

    await fetchRequiredCategories({ domain: 'quotes', ids: [2] })
    expect(postMock).toHaveBeenLastCalledWith('/assignment/required-categories', {
      domain: 'quotes',
      ids: [2],
    })
  })

  it('returns the empty union as-is (no requirement)', async () => {
    postMock.mockResolvedValue(envelope([]))

    await expect(fetchRequiredCategories({ domain: 'leads', ids: [1] })).resolves.toEqual([])
  })
})
