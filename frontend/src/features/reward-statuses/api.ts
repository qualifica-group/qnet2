import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateRewardStatusPayload,
  RewardStatusDetail,
  RewardStatusDetailWithPermissions,
  UpdateRewardStatusPayload,
} from '@/features/reward-statuses/types'

/**
 * Fetches a single reward status detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchRewardStatus(id: number): Promise<RewardStatusDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<RewardStatusDetail, ResourcePermissions>
  >(`/reward-statuses/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a reward status. Returns the created resource from the envelope `data`. */
export async function createRewardStatus(
  payload: CreateRewardStatusPayload,
): Promise<RewardStatusDetail> {
  const { data } = await apiClient.post<ApiResponse<RewardStatusDetail>>(
    '/reward-statuses',
    payload,
  )
  return data.data
}

/** Partially updates a reward status (PATCH). Returns the updated resource. */
export async function updateRewardStatus(
  id: number,
  payload: UpdateRewardStatusPayload,
): Promise<RewardStatusDetail> {
  const { data } = await apiClient.patch<ApiResponse<RewardStatusDetail>>(
    `/reward-statuses/${id}`,
    payload,
  )
  return data.data
}

/**
 * Deletes a reward status. Backend responds 204 with no body, or 409 when
 * the status is still referenced by a reward (BR-4) — the caller surfaces
 * the backend's exact `message` for that case.
 */
export async function deleteRewardStatus(id: number): Promise<void> {
  await apiClient.delete(`/reward-statuses/${id}`)
}
