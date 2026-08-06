import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateOpportunityPayload,
  OpportunityDetail,
  OpportunityDetailWithPermissions,
  UpdateOpportunityPayload,
} from '@/features/opportunities/types'

/** Table/stats domain key of this module, shared by the adapter and the stats invalidation. */
export const OPPORTUNITIES_DOMAIN = 'opportunities'

/**
 * Polymorphic owner alias of an opportunity (`config('attachments.attachable_types')`),
 * sent as `attachable_type` by the documents surfaces — singular, NOT the
 * plural domain key above. Shared with the request-management adapter, whose
 * rows ARE the same Opportunity records (spec 0049 D-1), so the two modules
 * can never drift onto different aliases.
 */
export const OPPORTUNITY_ATTACHABLE_ALIAS = 'opportunity'

/**
 * Query key of a single opportunity's detail (fresh-on-open pattern). Shared
 * by the detail/edit pages and by the post-mutation invalidation, so they can
 * never drift apart. `null` (an unparsable route param) is a key that is
 * never fetched.
 */
export function opportunityDetailQueryKey(id: number | null) {
  return ['opportunities', 'detail', id] as const
}

/**
 * Fetches a single opportunity detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchOpportunity(id: number): Promise<OpportunityDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<OpportunityDetail, ResourcePermissions>
  >(`/opportunities/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates an opportunity. Returns the created resource. */
export async function createOpportunity(
  payload: CreateOpportunityPayload,
): Promise<OpportunityDetail> {
  const { data } = await apiClient.post<ApiResponse<OpportunityDetail>>('/opportunities', payload)
  return data.data
}

/** Partially updates an opportunity (PATCH). Returns the updated resource. */
export async function updateOpportunity(
  id: number,
  payload: UpdateOpportunityPayload,
): Promise<OpportunityDetail> {
  const { data } = await apiClient.patch<ApiResponse<OpportunityDetail>>(
    `/opportunities/${id}`,
    payload,
  )
  return data.data
}

/** Deletes an opportunity. Backend responds 204 with no body. */
export async function deleteOpportunity(id: number): Promise<void> {
  await apiClient.delete(`/opportunities/${id}`)
}

export function categoryManagerLabelsQueryKey(categoryId: number) {
  return ['opportunities', 'category-manager-labels', categoryId] as const
}

/**
 * Resolves a Product Category's effective G.A. labels (spec 0080, frozen
 * contract owned by `features/product-categories`). Called directly here, not
 * imported from that feature (module decoupling): the form's team section
 * resolves the labels LIVE from the product lines being edited, not from the
 * persisted `OpportunityDetail.manager_labels` (see `use-opportunity-manager-labels.ts`).
 */
export async function fetchCategoryManagerLabels(categoryId: number): Promise<Record<string, string>> {
  const { data } = await apiClient.get<ApiResponse<{ manager_labels: Record<string, string> }>>(
    `/product-categories/${categoryId}/effective-manager-labels`,
  )
  return data.data.manager_labels
}
