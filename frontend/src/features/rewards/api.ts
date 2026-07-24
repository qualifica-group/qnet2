import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { RewardDetailItem } from '@/features/rewards/types'

/**
 * Updates the persisted status of a single reward (spec 0060 §4, D-1: card
 * inline edit). The backend accepts only an active `reward_status_id`
 * (BR-8/D-7) and enforces `rewarded-referents.update` plus the `reward_status`
 * field-permission server-side; the response mirrors the full
 * `RewardResource` shape consumed as `RewardDetailItem`.
 */
export async function patchRewardStatus(
  rewardId: number,
  rewardStatusId: number,
): Promise<RewardDetailItem> {
  const { data } = await apiClient.patch<ApiResponse<RewardDetailItem>>(`/rewards/${rewardId}`, {
    reward_status_id: rewardStatusId,
  })
  return data.data
}
