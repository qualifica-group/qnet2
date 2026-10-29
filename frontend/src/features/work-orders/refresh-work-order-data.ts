import type { QueryClient } from '@tanstack/react-query'
import { fetchWorkOrder, WORK_ORDERS_DOMAIN, workOrderDetailQueryKey } from '@/features/work-orders/api'

/**
 * Brings the cached work order data up to date after a task closed positively
 * (spec 0323 D-6): closing can move the commessa's Stato lavorazione and add a
 * progress entry. Progress entries are invalidated; every cached detail is
 * marked stale (an unmounted one refetches on its next open) and the ones on
 * screen are refetched and written back with `setQueryData` rather than
 * invalidated, because an invalidation would flip the open record
 * (`useEntityDetail`) back to its skeleton while the task panel inside it is
 * still visible — the same reason `useWorkOrderProgress` does it this way. The
 * work orders grid is an AG Grid datasource, not a query: it re-reads its rows
 * when next shown. A failed refresh is swallowed: the task action succeeded.
 */
export async function refreshWorkOrderData(queryClient: QueryClient): Promise<void> {
  // Built per call, not at module load: hosts that mock `work-orders/api` partially must still import this module.
  const detailKeyPrefix = [WORK_ORDERS_DOMAIN, 'detail'] as const
  const activeDetailIds = queryClient
    .getQueryCache()
    .findAll({ queryKey: detailKeyPrefix, type: 'active' })
    .map((query) => query.queryKey[2])
    .filter((id): id is number => typeof id === 'number')

  await Promise.allSettled([
    queryClient.invalidateQueries({ queryKey: [WORK_ORDERS_DOMAIN, 'progress-entries'] }),
    queryClient.invalidateQueries({ queryKey: detailKeyPrefix, refetchType: 'none' }),
    ...activeDetailIds.map(async (id) => {
      queryClient.setQueryData(workOrderDetailQueryKey(id), await fetchWorkOrder(id))
    }),
  ])
}
