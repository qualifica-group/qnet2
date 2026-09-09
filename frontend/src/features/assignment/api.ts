import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  RequiredCategoriesPayload,
  RequiredCategoriesResult,
} from '@/features/assignment/types'

/**
 * Resolves the product categories a selection of records requires
 * (`POST /assignment/required-categories`, spec 0110). POST — not GET —
 * because an SSRM selection can carry thousands of row ids.
 *
 * Returns the union of the requirements, ascending and deduplicated; an empty
 * array means the selection expresses no requirement at all.
 */
export async function fetchRequiredCategories(
  payload: RequiredCategoriesPayload,
): Promise<number[]> {
  const { data } = await apiClient.post<ApiResponse<RequiredCategoriesResult>>(
    '/assignment/required-categories',
    payload,
  )
  return data.data.product_category_ids
}
