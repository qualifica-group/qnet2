import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the contract-statuses for-select endpoint. */
export const CONTRACT_STATUSES_FOR_SELECT_RESOURCE = 'contract-statuses'

/**
 * Fetches a page of contract status options from
 * `GET /api/contract-statuses/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `contract-statuses` resource. Ungated by
 * permission (spec 0072 ADR 0011 amendment): any authenticated actor can
 * resolve options for this picker.
 */
export function fetchContractStatusesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(CONTRACT_STATUSES_FOR_SELECT_RESOURCE, params)
}

interface UseContractStatusesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a contract status single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `contract-statuses` resource.
 */
export function useContractStatusesForSelect({
  search,
  ids,
  enabled,
}: UseContractStatusesForSelectOptions) {
  return useForSelect({
    resource: CONTRACT_STATUSES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
