import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the units-of-measure for-select endpoint. */
export const UNITS_OF_MEASURE_FOR_SELECT_RESOURCE = 'units-of-measure'

/**
 * Fetches a page of unit of measure options from
 * `GET /api/units-of-measure/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `units-of-measure` resource.
 */
export function fetchUnitsOfMeasureForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(UNITS_OF_MEASURE_FOR_SELECT_RESOURCE, params)
}

interface UseUnitsOfMeasureForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a unit of measure single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `units-of-measure` resource.
 */
export function useUnitsOfMeasureForSelect({ search, ids, enabled }: UseUnitsOfMeasureForSelectOptions) {
  return useForSelect({
    resource: UNITS_OF_MEASURE_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
