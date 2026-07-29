import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateQuoteStatusPayload,
  QuoteStatusDetail,
  QuoteStatusDetailWithPermissions,
  UpdateQuoteStatusPayload,
} from '@/features/quote-statuses/types'

/**
 * Fetches a single quote status detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchQuoteStatus(id: number): Promise<QuoteStatusDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<QuoteStatusDetail, ResourcePermissions>
  >(`/quote-statuses/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a quote status. Returns the created resource from the envelope `data`. */
export async function createQuoteStatus(
  payload: CreateQuoteStatusPayload,
): Promise<QuoteStatusDetail> {
  const { data } = await apiClient.post<ApiResponse<QuoteStatusDetail>>(
    '/quote-statuses',
    payload,
  )
  return data.data
}

/** Partially updates a quote status (PATCH). Returns the updated resource. */
export async function updateQuoteStatus(
  id: number,
  payload: UpdateQuoteStatusPayload,
): Promise<QuoteStatusDetail> {
  const { data } = await apiClient.patch<ApiResponse<QuoteStatusDetail>>(
    `/quote-statuses/${id}`,
    payload,
  )
  return data.data
}

/**
 * Deletes a quote status. Backend responds 200 with no data, or 409 when the
 * status is still referenced by a Quote — the caller surfaces the backend's
 * exact `message` for that case.
 */
export async function deleteQuoteStatus(id: number): Promise<void> {
  await apiClient.delete(`/quote-statuses/${id}`)
}
