import { useInfiniteQuery } from '@tanstack/react-query'
import { listWorkOrderEmails, workOrderEmailsListQueryKey, type WorkOrderEmailsPage } from '@/features/work-order-emails/api'

/** Poll cadence while at least one row is still `queued` (AC-019: transient state before sent/failed). */
const QUEUED_POLL_INTERVAL_MS = 5_000

function hasQueuedEmail(pages: WorkOrderEmailsPage[] | undefined): boolean {
  return (pages ?? []).some((page) => page.data.some((item) => item.status === 'queued'))
}

/**
 * "Load more" feed of the commessa's email history (20/page, `updated_at`
 * desc, server-side — mirrors `useActivityLog`'s own page-param shape).
 * Self-polls only while a `queued` row is on a loaded page: once every row
 * settles to `sent`/`failed` the interval turns itself off.
 */
export function useWorkOrderEmailsList(workOrderId: number) {
  return useInfiniteQuery({
    queryKey: workOrderEmailsListQueryKey(workOrderId),
    queryFn: ({ pageParam }) => listWorkOrderEmails(workOrderId, pageParam),
    initialPageParam: 1,
    getNextPageParam: (lastPage) =>
      lastPage.meta.current_page < lastPage.meta.last_page ? lastPage.meta.current_page + 1 : undefined,
    refetchInterval: (query) => (hasQueuedEmail(query.state.data?.pages) ? QUEUED_POLL_INTERVAL_MS : false),
  })
}
