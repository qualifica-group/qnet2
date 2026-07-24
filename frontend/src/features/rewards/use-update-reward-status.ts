import { useMutation, type UseMutationResult } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { patchRewardStatus } from '@/features/rewards/api'
import type { RewardDetailItem } from '@/features/rewards/types'

export interface UpdateRewardStatusVariables {
  rewardId: number
  rewardStatusId: number
}

interface UseUpdateRewardStatusOptions {
  /**
   * Fired after a successful PATCH. Invalidating the referent's reward list
   * query is the caller's job (`rewarded-referents` owns that query key), not
   * this feature's — keeps `features/rewards/` free of a reverse dependency
   * on `rewarded-referents`.
   */
  onSuccess?: (reward: RewardDetailItem, variables: UpdateRewardStatusVariables) => void
}

/**
 * Mutation behind the reward card's inline status edit (spec 0060 §4,
 * AC-029). One shared instance can serve every card in a detail panel: the
 * caller tracks in-flight state per card via `mutation.variables.rewardId`.
 */
export function useUpdateRewardStatus(
  options: UseUpdateRewardStatusOptions = {},
): UseMutationResult<RewardDetailItem, AxiosError, UpdateRewardStatusVariables> {
  return useMutation({
    mutationFn: ({ rewardId, rewardStatusId }: UpdateRewardStatusVariables) =>
      patchRewardStatus(rewardId, rewardStatusId),
    onSuccess: options.onSuccess,
  })
}
