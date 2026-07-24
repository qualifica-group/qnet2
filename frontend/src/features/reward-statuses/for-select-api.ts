import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the reward-statuses for-select endpoint. */
export const REWARD_STATUSES_FOR_SELECT_RESOURCE = 'reward-statuses'

/**
 * Fetches a page of reward status options from
 * `GET /api/reward-statuses/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `reward-statuses` resource. Only active
 * statuses are returned, ordered `sort_order,name,id` (BR-5).
 */
export function fetchRewardStatusesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(REWARD_STATUSES_FOR_SELECT_RESOURCE, params)
}

interface UseRewardStatusesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a reward status single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `reward-statuses` resource.
 */
export function useRewardStatusesForSelect({
  search,
  ids,
  enabled,
}: UseRewardStatusesForSelectOptions) {
  return useForSelect({
    resource: REWARD_STATUSES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
