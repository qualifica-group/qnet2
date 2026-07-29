import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the quote-statuses for-select endpoint. */
export const QUOTE_STATUSES_FOR_SELECT_RESOURCE = 'quote-statuses'

/**
 * Fetches a page of quote status options from
 * `GET /api/quote-statuses/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `quote-statuses` resource. Consumed by the
 * Quote form (spec 0065) to pick the quote's status.
 */
export function fetchQuoteStatusesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(QUOTE_STATUSES_FOR_SELECT_RESOURCE, params)
}

interface UseQuoteStatusesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a quote status single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `quote-statuses` resource.
 */
export function useQuoteStatusesForSelect({
  search,
  ids,
  enabled,
}: UseQuoteStatusesForSelectOptions) {
  return useForSelect({
    resource: QUOTE_STATUSES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
