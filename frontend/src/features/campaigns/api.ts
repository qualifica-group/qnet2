import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CampaignDetail,
  CampaignDetailWithPermissions,
  CreateCampaignPayload,
  UpdateCampaignPayload,
} from '@/features/campaigns/types'

/**
 * Query key of a single campaign's detail (fresh-on-open pattern). Shared by
 * the detail/edit pages and by the post-mutation invalidation, so they can
 * never drift apart. `null` (an unparsable route param) is a key that is never
 * fetched.
 */
export function campaignDetailQueryKey(id: number | null) {
  return ['campaigns', 'detail', id] as const
}

/**
 * Fetches a single campaign detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchCampaign(id: number): Promise<CampaignDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<CampaignDetail, ResourcePermissions>
  >(`/campaigns/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/**
 * The next sequential code (CMP-0001...) suggested for the create form's
 * `code` auto-fill (spec 0025). Non-binding: the user may edit it, and the
 * server resolves the definitive value atomically on store.
 */
export async function fetchCampaignNextCode(): Promise<string> {
  const { data } = await apiClient.get<ApiResponse<{ code: string }>>('/campaigns/next-code')
  return data.data.code
}

/** Creates a campaign. `code` is server-generated (BR-1); returns the created resource. */
export async function createCampaign(payload: CreateCampaignPayload): Promise<CampaignDetail> {
  const { data } = await apiClient.post<ApiResponse<CampaignDetail>>('/campaigns', payload)
  return data.data
}

/**
 * Partially updates a campaign (PATCH). Returns the updated resource with the
 * actor's re-evaluated permissions, so the in-place detail (spec 0221) keeps
 * gating its rows on fresh values.
 */
export async function updateCampaign(
  id: number,
  payload: UpdateCampaignPayload,
): Promise<CampaignDetailWithPermissions> {
  const { data } = await apiClient.patch<ApiResponseWithPermissions<CampaignDetail, ResourcePermissions>>(
    `/campaigns/${id}`,
    payload,
  )
  return { ...data.data, permissions: data.permissions }
}

/** Deletes a campaign. Backend responds 204 with no body. */
export async function deleteCampaign(id: number): Promise<void> {
  await apiClient.delete(`/campaigns/${id}`)
}
