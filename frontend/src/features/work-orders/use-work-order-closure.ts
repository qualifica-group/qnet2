import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Lock, LockOpen } from 'lucide-react'
import { updateWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import { taskBoardKeys } from '@/features/work-orders/task-board/query-keys'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { UpdateWorkOrderPayload, WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/** Icons of the Commesse grid's closure row actions (`WorkOrderColumnCatalog::actions()`). */
export const WORK_ORDER_ACTION_ICONS: ActionIconMap = {
  lock: Lock,
  'lock-open': LockOpen,
}

/** TanStack prefix of every task list/detail: a forced closure closes the open ones (spec 0146 D-8). */
const TASKS_QUERY_PREFIX = ['tasks'] as const

interface ClosureVariables {
  workOrderId: number
  payload: Pick<UpdateWorkOrderPayload, 'is_force_closed' | 'force_close_reason'>
}

/** "Chiusura forzata" with its reason (D-4/AC-073): the reason is required server-side too. */
export function forceClosePayload(reason: string): ClosureVariables['payload'] {
  return { is_force_closed: true, force_close_reason: reason }
}

/** "Riapri": the reason is meaningless once the closure is no longer forced (D-4). */
export const REOPEN_PAYLOAD: ClosureVariables['payload'] = { is_force_closed: false, force_close_reason: null }

/**
 * Forces a work order closed, or reopens it — an ACTION, not a field (user
 * directive 2026-10-06): the same partial PATCH the old switch sent. On
 * success the detail cache takes the saved record (permissions included,
 * so the action flips to its inverse), and the task board and task lists
 * refetch: forcing the closure closes every open task with a negative
 * outcome server-side.
 */
export function useWorkOrderClosure(onDone?: (saved: WorkOrderDetailWithPermissions) => void) {
  const queryClient = useQueryClient()

  return useMutation<WorkOrderDetailWithPermissions, unknown, ClosureVariables>({
    mutationFn: ({ workOrderId, payload }) => updateWorkOrder(workOrderId, payload),
    onSuccess: async (saved, { workOrderId }) => {
      queryClient.setQueryData(workOrderDetailQueryKey(workOrderId), saved)
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: taskBoardKeys.board(workOrderId) }),
        queryClient.invalidateQueries({ queryKey: TASKS_QUERY_PREFIX }),
      ])
      onDone?.(saved)
    },
  })
}
