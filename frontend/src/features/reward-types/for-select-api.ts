import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the reward-types for-select endpoint. */
export const REWARD_TYPES_FOR_SELECT_RESOURCE = 'reward-types'

/**
 * Fetches a page of reward type options from
 * `GET /api/reward-types/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `reward-types` resource. No relation
 * field consumes it yet (D-7): it exists as the module's standard surface,
 * ready for a future relation select.
 */
export function fetchRewardTypesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(REWARD_TYPES_FOR_SELECT_RESOURCE, params)
}

interface UseRewardTypesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a reward type single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `reward-types` resource.
 */
export function useRewardTypesForSelect({ search, ids, enabled }: UseRewardTypesForSelectOptions) {
  return useForSelect({
    resource: REWARD_TYPES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
