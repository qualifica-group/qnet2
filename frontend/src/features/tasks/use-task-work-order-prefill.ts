import { useQuery } from '@tanstack/react-query'
import { fetchWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import type { RelationFieldRef } from '@/components/form/relation-select-field'

/**
 * Picker hydration for a create form opened from the Commessa detail's Task
 * tab (spec 0133 D-4): the form value itself is seeded by `createDefaults`,
 * this only resolves its `{id, name}` label. Reads the SAME query key as the
 * work order detail mounted underneath, so the label comes from cache with
 * no second request (`refetchOnMount: false`, like `useTaskParentPrefill`).
 * `null` while there is no work order or it has not loaded yet.
 */
export function useTaskWorkOrderPrefill(workOrderId: number | null): RelationFieldRef | null {
  const { data: workOrder } = useQuery({
    queryKey: workOrderDetailQueryKey(workOrderId ?? 0),
    queryFn: () => fetchWorkOrder(workOrderId as number),
    enabled: workOrderId !== null,
    refetchOnMount: false,
  })

  if (workOrderId === null || !workOrder) {
    return null
  }
  const title = workOrder.title.trim()
  return { id: workOrder.id, name: title === '' ? workOrder.code : `${workOrder.code} — ${title}` }
}
