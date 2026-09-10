import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  AssignmentScopePayload,
  AssignmentScopeResult,
} from '@/features/assignment/types'

/**
 * Resolves the assignment scope of a selection of records
 * (`POST /assignment/selection-scope`, spec 0113). POST — not GET — because an
 * SSRM selection can carry thousands of row ids.
 *
 * Returns the required categories (union, ascending, deduplicated), the shared
 * operational site (`null` when mixed) and the campaigns the selection spans.
 */
export async function fetchAssignmentScope(
  payload: AssignmentScopePayload,
): Promise<AssignmentScopeResult> {
  const { data } = await apiClient.post<ApiResponse<AssignmentScopeResult>>(
    '/assignment/selection-scope',
    payload,
  )
  return data.data
}
