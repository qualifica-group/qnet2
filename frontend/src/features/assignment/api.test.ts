import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchAssignmentScope } from '@/features/assignment/api'
import { apiClient } from '@/api/client'

vi.mock('@/api/client', () => ({
  apiClient: { post: vi.fn() },
}))

const postMock = vi.mocked(apiClient.post)

/** Standard `{ success, message, data }` envelope as seen by axios. */
function envelope(overrides: {
  product_category_ids?: number[]
  operational_site_id?: number | null
  campaign_ids?: number[]
  single_operator_available?: boolean
}) {
  const data = {
    product_category_ids: [],
    operational_site_id: null,
    campaign_ids: [],
    single_operator_available: true,
    ...overrides,
  }
  return { data: { success: true, message: 'ok', data } }
}

beforeEach(() => {
  postMock.mockReset()
})

describe('fetchAssignmentScope', () => {
  it('posts an SSRM import-rows selection verbatim and unwraps the whole scope', async () => {
    postMock.mockResolvedValue(
      envelope({ product_category_ids: [3, 7], operational_site_id: 5, campaign_ids: [2] }),
    )

    const result = await fetchAssignmentScope({
      domain: 'import_rows',
      import_run_id: 12,
      select_all: true,
      row_ids: [4, 5],
    })

    expect(result).toEqual({
      product_category_ids: [3, 7],
      operational_site_id: 5,
      campaign_ids: [2],
      single_operator_available: true,
    })
    expect(postMock).toHaveBeenCalledWith('/assignment/selection-scope', {
      domain: 'import_rows',
      import_run_id: 12,
      select_all: true,
      row_ids: [4, 5],
    })
  })

  it('posts an id-based selection for the leads and quotes domains', async () => {
    postMock.mockResolvedValue(
      envelope({ product_category_ids: [1], operational_site_id: 1, campaign_ids: [] }),
    )

    await fetchAssignmentScope({ domain: 'leads', ids: [8, 9] })
    expect(postMock).toHaveBeenCalledWith('/assignment/selection-scope', {
      domain: 'leads',
      ids: [8, 9],
    })

    await fetchAssignmentScope({ domain: 'quotes', ids: [2] })
    expect(postMock).toHaveBeenLastCalledWith('/assignment/selection-scope', {
      domain: 'quotes',
      ids: [2],
    })
  })

  it('returns a mixed-site scope with no common operator as-is', async () => {
    postMock.mockResolvedValue(
      envelope({ campaign_ids: [4, 9], single_operator_available: false }),
    )

    await expect(fetchAssignmentScope({ domain: 'leads', ids: [1] })).resolves.toEqual({
      product_category_ids: [],
      operational_site_id: null,
      campaign_ids: [4, 9],
      single_operator_available: false,
    })
  })
})
