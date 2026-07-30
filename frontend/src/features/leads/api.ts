import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  AssignOperatorsPayload,
  AssignOperatorsResult,
  ConvertLeadsPayload,
  ConvertLeadsResult,
  CreateLeadPayload,
  LeadDetail,
  LeadDetailWithPermissions,
  UpdateLeadPayload,
} from '@/features/leads/types'

/**
 * Query key of a single lead's detail (fresh-on-open pattern). Shared by
 * the detail/edit pages and by the post-mutation invalidation, so they can
 * never drift apart. `null` (an unparsable route param) is a key that is never
 * fetched.
 */
export function leadDetailQueryKey(id: number | null) {
  return ['leads', 'detail', id] as const
}

/**
 * Fetches a single lead detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchLead(id: number): Promise<LeadDetailWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<LeadDetail, ResourcePermissions>>(
    `/leads/${id}`,
  )
  return { ...data.data, permissions: data.permissions }
}

/** Creates a lead. Returns the created resource. */
export async function createLead(payload: CreateLeadPayload): Promise<LeadDetail> {
  const { data } = await apiClient.post<ApiResponse<LeadDetail>>('/leads', payload)
  return data.data
}

/** Partially updates a lead (PATCH). Returns the updated resource. */
export async function updateLead(id: number, payload: UpdateLeadPayload): Promise<LeadDetail> {
  const { data } = await apiClient.patch<ApiResponse<LeadDetail>>(`/leads/${id}`, payload)
  return data.data
}

/** Deletes a lead. Backend responds 204 with no body. */
export async function deleteLead(id: number): Promise<void> {
  await apiClient.delete(`/leads/${id}`)
}

/**
 * Unified bulk operator assignment (spec 0048): assigns `operational_site_id`
 * to every lead in `lead_ids`, plus either a single `operator_id` (mode
 * `'single'`) or a load-balanced split across the Sede's operators (mode
 * `'balanced'`). Returns how many leads were updated.
 */
export async function assignLeadOperators(
  payload: AssignOperatorsPayload,
): Promise<AssignOperatorsResult> {
  const { data } = await apiClient.post<ApiResponse<AssignOperatorsResult>>(
    '/leads/assign-operators',
    payload,
  )
  return data.data
}

/**
 * Mass Lead -> Opportunity conversion (spec 0071). Unlike the single row
 * action, which opens the prefilled Opportunity form, this derives every
 * Opportunity server-side. All-or-nothing: a batch holding a lead that cannot
 * be converted answers 422 with `errors.blockers` and converts nothing.
 */
export async function convertLeadsToOpportunities(
  payload: ConvertLeadsPayload,
): Promise<ConvertLeadsResult> {
  const { data } = await apiClient.post<ApiResponse<ConvertLeadsResult>>(
    '/leads/convert-to-opportunities',
    payload,
  )
  return data.data
}
