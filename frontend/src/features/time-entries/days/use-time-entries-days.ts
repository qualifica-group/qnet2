/**
 * Infinite-scrolls the day-grouped dashboard list (`GET /api/time-entries`,
 * spec 0122 data_contract). Pages are 1-based (`per_page`=`TIME_ENTRIES_PER_PAGE`,
 * D-13); the next page is requested while `pagination.offset + pagination.limit
 * < pagination.total` (MT-F1 contract note), matching the backend's own offset
 * bookkeeping instead of trusting a client-side page counter.
 */

import { useInfiniteQuery } from '@tanstack/react-query'
import { fetchTimeEntries } from '@/features/time-entries/api'
import { timeEntryKeys } from '@/features/time-entries/query-keys'
import { TIME_ENTRIES_PER_PAGE } from '@/features/time-entries/time-entry-constants'
import {
  buildTimeEntriesListParams,
  type TimeEntriesFiltersState,
} from '@/features/time-entries/time-entries-filters'
import type { DaySummary, TimeEntriesListMeta } from '@/features/time-entries/types'

interface UseTimeEntriesDaysArgs {
  filters: TimeEntriesFiltersState
  /** Gates the query, e.g. until the page's stored filters have hydrated. */
  enabled: boolean
}

export interface UseTimeEntriesDaysResult {
  days: DaySummary[]
  /** From the first loaded page — identical on every page of the same query. */
  meta: TimeEntriesListMeta | undefined
  isLoading: boolean
  isError: boolean
  hasNextPage: boolean
  isFetchingNextPage: boolean
  fetchNextPage: () => void
}

/** Resolves the next `page` from the last page's `pagination` envelope, or none once exhausted. */
function resolveNextPage(pagination: { offset: number; limit: number; total: number }): number | undefined {
  if (pagination.offset + pagination.limit >= pagination.total) {
    return undefined
  }
  return Math.floor(pagination.offset / pagination.limit) + 2
}

export function useTimeEntriesDays({ filters, enabled }: UseTimeEntriesDaysArgs): UseTimeEntriesDaysResult {
  const query = useInfiniteQuery({
    queryKey: timeEntryKeys.list(buildTimeEntriesListParams(filters, 1, TIME_ENTRIES_PER_PAGE)),
    queryFn: ({ pageParam }) =>
      fetchTimeEntries(buildTimeEntriesListParams(filters, pageParam, TIME_ENTRIES_PER_PAGE)),
    initialPageParam: 1,
    getNextPageParam: (lastPage) => resolveNextPage(lastPage.pagination),
    enabled,
  })

  return {
    days: query.data?.pages.flatMap((page) => page.items) ?? [],
    meta: query.data?.pages[0]?.meta,
    isLoading: query.isLoading,
    isError: query.isError,
    hasNextPage: Boolean(query.hasNextPage),
    isFetchingNextPage: query.isFetchingNextPage,
    fetchNextPage: () => {
      void query.fetchNextPage()
    },
  }
}
