import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateQuoteWorkflowPayload,
  CriterionFieldOption,
  QuoteWorkflowDetail,
  QuoteWorkflowDetailWithPermissions,
  QuoteWorkflowStatusItem,
  UpdateDefaultStatusesPayload,
  UpdateQuoteWorkflowPayload,
} from '@/features/quote-workflows/types'

/**
 * Fetches a single opportunity workflow detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchQuoteWorkflow(id: number): Promise<QuoteWorkflowDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<QuoteWorkflowDetail, ResourcePermissions>
  >(`/quote-workflows/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates an opportunity workflow (criteria + optional custom statuses in one request). */
export async function createQuoteWorkflow(
  payload: CreateQuoteWorkflowPayload,
): Promise<QuoteWorkflowDetail> {
  const { data } = await apiClient.post<ApiResponse<QuoteWorkflowDetail>>(
    '/quote-workflows',
    payload,
  )
  return data.data
}

/** Updates an opportunity workflow. `criteria`/`statuses`, when present, are authoritative syncs. */
export async function updateQuoteWorkflow(
  id: number,
  payload: UpdateQuoteWorkflowPayload,
): Promise<QuoteWorkflowDetail> {
  const { data } = await apiClient.patch<ApiResponse<QuoteWorkflowDetail>>(
    `/quote-workflows/${id}`,
    payload,
  )
  return data.data
}

/**
 * Deletes an opportunity workflow. The backend re-resolves every impacted
 * Opportunity in the same transaction (AC-018) — no cleanup needed here.
 */
export async function deleteQuoteWorkflow(id: number): Promise<void> {
  await apiClient.delete(`/quote-workflows/${id}`)
}

/** Fetches the allow-listed criterion fields (AC-022), for the criteria editor's field select. */
export async function fetchCriterionFields(): Promise<CriterionFieldOption[]> {
  const { data } = await apiClient.get<ApiResponse<CriterionFieldOption[]>>(
    '/quote-workflows/criterion-fields',
  )
  return data.data
}

/** Fetches the GLOBAL default status set, ordered by `sort_order`. */
export async function fetchDefaultStatuses(): Promise<QuoteWorkflowStatusItem[]> {
  const { data } = await apiClient.get<ApiResponse<QuoteWorkflowStatusItem[]>>(
    '/quote-workflows/default-statuses',
  )
  return data.data
}

/** Syncs the GLOBAL default status set's custom rows + order. Returns the fresh, full ordered list. */
export async function updateDefaultStatuses(
  payload: UpdateDefaultStatusesPayload,
): Promise<QuoteWorkflowStatusItem[]> {
  const { data } = await apiClient.put<ApiResponse<QuoteWorkflowStatusItem[]>>(
    '/quote-workflows/default-statuses',
    payload,
  )
  return data.data
}
