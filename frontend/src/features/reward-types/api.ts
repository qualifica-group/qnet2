import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateRewardTypePayload,
  RewardTypeDetail,
  RewardTypeDetailWithPermissions,
  UpdateRewardTypePayload,
} from '@/features/reward-types/types'

/** Query key of a single reward type's detail, shared by the detail screen and its in-place saves. */
export function rewardTypeDetailQueryKey(id: number) {
  return ['reward-types', 'detail', id] as const
}

/**
 * Fetches a single reward type detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchRewardType(id: number): Promise<RewardTypeDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<RewardTypeDetail, ResourcePermissions>
  >(`/reward-types/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a reward type. Returns the created resource from the envelope `data`. */
export async function createRewardType(
  payload: CreateRewardTypePayload,
): Promise<RewardTypeDetail> {
  const { data } = await apiClient.post<ApiResponse<RewardTypeDetail>>('/reward-types', payload)
  return data.data
}

/**
 * Partially updates a reward type (PATCH). Returns the saved record with the actor's
 * permissions, so the in-place detail re-syncs on it as is.
 */
export async function updateRewardType(
  id: number,
  payload: UpdateRewardTypePayload,
): Promise<RewardTypeDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<RewardTypeDetail, ResourcePermissions>
  >(`/reward-types/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}

/**
 * Deletes a reward type. Backend responds 204 with no body. This version has
 * no delete-guard (BR-3): a 409 branch is still handled by the caller's toast
 * as a zero-cost point of extension for when a future module references
 * `reward_types`.
 */
export async function deleteRewardType(id: number): Promise<void> {
  await apiClient.delete(`/reward-types/${id}`)
}
