/**
 * Spec 0154 D-11: resolves the REGISTRY a given commessa belongs to, so
 * `useTaskForm.handleRegistryChange` can tell whether the linked
 * `work_order_id` survives an anagrafica change ("mantiene la commessa solo
 * se della stessa anagrafica"). `TaskWorkOrderRef` (`task.work_order`) carries
 * no `registry_id` of its own, so this reuses the work-orders for-select
 * endpoint's `ids[]` hydration (frozen contract: every item now exposes
 * `meta.registry_id`) instead of inventing a new endpoint — the same
 * `fetchForSelect` primitive every other picker in this form already uses,
 * kept under its OWN query key so it never collides with the picker's own
 * paginated list cache (`forSelectKeys.list`).
 */

import { useQuery } from '@tanstack/react-query'
import { fetchForSelect } from '@/features/for-select/api'

/** The `meta` bag the work-orders for-select endpoint now carries (spec 0154 contract). */
interface WorkOrderForSelectMeta {
  registry_id: number | null
}

/** Resource segment of the work orders for-select endpoint (mirrors `task-links-section.tsx`). */
const WORK_ORDERS_FOR_SELECT_RESOURCE = 'work-orders'

async function fetchWorkOrderRegistryId(workOrderId: number): Promise<number | null> {
  const response = await fetchForSelect(WORK_ORDERS_FOR_SELECT_RESOURCE, {
    ids: [workOrderId],
    limit: 1,
  })
  const item = response.items.find((option) => option.id === workOrderId)
  const meta = (item as { meta?: WorkOrderForSelectMeta } | undefined)?.meta
  return meta?.registry_id ?? null
}

/**
 * `null` while there is no work order, before the request resolves, or when
 * the picked commessa carries no registry — the cascade in `useTaskForm`
 * treats an unknown registry as "do not clear", never as a mismatch, so a
 * slow/failed lookup degrades to keeping the commessa rather than losing it.
 */
export function useTaskWorkOrderRegistryId(workOrderId: number | null): number | null {
  const { data } = useQuery({
    queryKey: ['work-orders', 'registry-id', workOrderId],
    queryFn: () => fetchWorkOrderRegistryId(workOrderId as number),
    enabled: workOrderId !== null,
  })
  return workOrderId === null ? null : (data ?? null)
}
