import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { RevenueTypeForSelectData, RevenueTypeForSelectParams } from '@/features/revenue-types/types'

/** Page size of the picker: the chart is small, the search narrows the rest. */
export const REVENUE_TYPES_PAGE_SIZE = 100

/**
 * Fetches the selectable revenue sub-accounts, grouped client-side by group > account.
 * The contract wraps the payload in the standard envelope; a bare payload is accepted too
 * (the sibling for-select endpoints answer unwrapped).
 */
export async function fetchRevenueTypesForSelect(
  params: RevenueTypeForSelectParams = {},
): Promise<RevenueTypeForSelectData> {
  const { search, ids, offset = 0, limit = REVENUE_TYPES_PAGE_SIZE } = params
  const { data } = await apiClient.get<ApiResponse<RevenueTypeForSelectData> | RevenueTypeForSelectData>(
    '/revenue-types/for-select',
    {
      params: {
        offset,
        limit,
        ...(search ? { search } : {}),
        ...(ids && ids.length > 0 ? { ids } : {}),
      },
      paramsSerializer: { indexes: true },
    },
  )
  return 'items' in data ? data : data.data
}
