import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { RewardDetailItem } from '@/features/rewards/types'
import type { ReferentRewardsResponse } from '@/features/rewarded-referents/types'

/**
 * Fetches the reward assignments of a single Referent (spec 0059 §3), for the
 * master/detail lazy load. Ordered by `assigned_at` desc server-side; the
 * frontend does not re-sort.
 */
export async function fetchReferentRewards(referentId: number): Promise<RewardDetailItem[]> {
  const { data } = await apiClient.get<ApiResponse<ReferentRewardsResponse>>(
    `/referents/${referentId}/rewards`,
  )
  return data.data.items
}
