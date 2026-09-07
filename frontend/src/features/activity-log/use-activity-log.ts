import { useInfiniteQuery } from '@tanstack/react-query'
import { ACTIVITY_LOG_DEFAULT_PAGE_SIZE, fetchActivityLog } from '@/features/activity-log/api'
import { activityLogKeys } from '@/features/activity-log/query-keys'
import type { ActivityLogEventFilter } from '@/features/activity-log/types'

/**
 * Infinite-scroll feed for a resource record's aggregated activity log
 * (spec 0034). The next page param is the opaque keyset cursor the backend
 * returns as `next_cursor`; `getNextPageParam` returns `undefined` once it is
 * `null`, which is what sets TanStack Query's `hasNextPage` to `false`.
 *
 * `event` is part of the query key: narrowing the feed is a server-side
 * filter, so each filter owns its own cached, independently paginated list.
 */
export function useActivityLog(
  resource: string,
  id: number,
  event: ActivityLogEventFilter = 'all',
  enabled = true,
) {
  return useInfiniteQuery({
    queryKey: activityLogKeys.list(resource, id, event),
    queryFn: ({ pageParam }) =>
      fetchActivityLog(resource, id, pageParam, ACTIVITY_LOG_DEFAULT_PAGE_SIZE, event),
    initialPageParam: null as string | null,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
    enabled,
  })
}
