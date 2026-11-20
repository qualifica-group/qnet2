import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateQuoteSystemStatusPayload,
  QuoteSystemStatusDetail,
  QuoteSystemStatusDetailWithPermissions,
  UpdateQuoteSystemStatusPayload,
} from '@/features/quote-system-statuses/types'

/** Query key of a single system status's detail, shared by the detail screen and its in-place saves. */
export function quoteSystemStatusDetailQueryKey(id: number) {
  return ['quote-system-statuses', 'detail', id] as const
}

/** Fetches a system status together with the actor's authorization metadata for it. */
export async function fetchQuoteSystemStatus(id: number): Promise<QuoteSystemStatusDetailWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<QuoteSystemStatusDetail, ResourcePermissions>>(
    `/quote-system-statuses/${id}`,
  )
  return { ...data.data, permissions: data.permissions }
}

export async function createQuoteSystemStatus(payload: CreateQuoteSystemStatusPayload): Promise<QuoteSystemStatusDetail> {
  const { data } = await apiClient.post<ApiResponse<QuoteSystemStatusDetail>>('/quote-system-statuses', payload)
  return data.data
}

/** Partially updates a system status (PATCH); returns the saved record with the actor's permissions. */
export async function updateQuoteSystemStatus(
  id: number,
  payload: UpdateQuoteSystemStatusPayload,
): Promise<QuoteSystemStatusDetailWithPermissions> {
  const { data } = await apiClient.patch<ApiResponseWithPermissions<QuoteSystemStatusDetail, ResourcePermissions>>(
    `/quote-system-statuses/${id}`,
    payload,
  )
  return { ...data.data, permissions: data.permissions }
}

/**
 * Deletes a system status. 422 on a system row, 409 while working statuses
 * still link it: the caller surfaces the backend `message`, which lists them.
 */
export async function deleteQuoteSystemStatus(id: number): Promise<void> {
  await apiClient.delete(`/quote-system-statuses/${id}`)
}
