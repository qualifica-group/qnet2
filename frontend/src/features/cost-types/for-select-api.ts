import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'
import { COST_TYPES_DOMAIN } from '@/features/cost-types/types'

/** Resource segment for the cost-types for-select endpoint (active types only, by sort_order then name). */
export const COST_TYPES_FOR_SELECT_RESOURCE = COST_TYPES_DOMAIN

/** Fetches a page of cost type options from `GET /api/cost-types/for-select`. */
export function fetchCostTypesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(COST_TYPES_FOR_SELECT_RESOURCE, params)
}

interface UseCostTypesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/** Hook feeding a cost type single-select: debounced search, pagination and `ids[]` hydration. */
export function useCostTypesForSelect({ search, ids, enabled }: UseCostTypesForSelectOptions) {
  return useForSelect({ resource: COST_TYPES_FOR_SELECT_RESOURCE, search, ids, enabled })
}
