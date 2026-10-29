import { useForSelect, useForSelectLabels } from '@/features/for-select/use-for-select'
import type { ForSelectItem } from '@/features/for-select/types'

/** Resource segment of the work order workflow statuses for-select endpoint (spec 0323 D-5). */
export const WORK_ORDER_WORKFLOW_STATUSES_FOR_SELECT_RESOURCE = 'work-order-workflow-statuses'

/**
 * The option fields this endpoint projects (frozen contract, spec 0323):
 * unlike most for-select resources it answers `name` and the status
 * presentation fields directly, with no `label`/`meta` bag. `work_order_workflow_id`
 * and `work_order_workflow_name` are `null` for the default set.
 */
export interface WorkOrderStatusForSelectFields {
  id: number
  name: string
  color: string | null
  progress_percentage: number | null
  system_key: 'open' | 'completed' | 'closed' | null
  work_order_workflow_id: number | null
  work_order_workflow_name: string | null
}

/** Narrows a generic for-select item to this endpoint's own shape. */
export function isWorkOrderStatusItem(item: ForSelectItem): item is ForSelectItem & WorkOrderStatusForSelectFields {
  return 'work_order_workflow_name' in item
}

interface UseWorkOrderStatusesForSelectOptions {
  search: string
}

/** Debounced server search and pagination over every work order workflow status. */
export function useWorkOrderStatusesForSelect({ search }: UseWorkOrderStatusesForSelectOptions) {
  return useForSelect({
    resource: WORK_ORDER_WORKFLOW_STATUSES_FOR_SELECT_RESOURCE,
    search,
  })
}

/**
 * Labels of the saved statuses, keyed by ids: the option list is one cache
 * shared by every row of the board, so its own `ids` would reach the server
 * for one row only.
 */
export function useWorkOrderStatusLabels(ids: number[]) {
  return useForSelectLabels({ resource: WORK_ORDER_WORKFLOW_STATUSES_FOR_SELECT_RESOURCE, ids })
}
