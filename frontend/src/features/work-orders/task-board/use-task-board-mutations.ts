import { useCallback } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { ApiErrorResponse } from '@/api/types'
import {
  bulkBoardTaskAction,
  closeWorkOrderStage,
  createWorkOrderStage,
  deleteWorkOrderStage,
  moveBoardTask,
  renameWorkOrderStage,
  reopenWorkOrderStage,
  reorderWorkOrderStages,
} from '@/features/work-orders/task-board/api'
import { workOrderDetailQueryKey } from '@/features/work-orders/api'
import { taskBoardKeys } from '@/features/work-orders/task-board/query-keys'
import { applyBoardMove } from '@/features/work-orders/task-board/task-board-move'
import type {
  BulkTaskPayload,
  BulkTaskResult,
  MoveBoardTaskPayload,
  MoveBoardTaskResult,
  TaskBoardPayload,
  WorkOrderStage,
} from '@/features/work-orders/task-board/types'

/**
 * Refreshes the board AND the commessa detail: a task change can move the
 * commessa's computed status and completion (spec 0149 AC-017), which the
 * detail header reads from its own cached query.
 */
export function useInvalidateTaskBoard(workOrderId: number) {
  const queryClient = useQueryClient()
  return useCallback(
    () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: taskBoardKeys.board(workOrderId) }),
        queryClient.invalidateQueries({ queryKey: workOrderDetailQueryKey(workOrderId) }),
      ]),
    [queryClient, workOrderId],
  )
}

/** Every fase write (rename/delete/reorder/close/reopen) refreshes the board (and the commessa detail) plus the standalone list the task form's "Fase" select reads. */
function useInvalidateStages(workOrderId: number) {
  const queryClient = useQueryClient()
  const invalidateBoard = useInvalidateTaskBoard(workOrderId)
  return () =>
    Promise.all([invalidateBoard(), queryClient.invalidateQueries({ queryKey: taskBoardKeys.stages(workOrderId) })])
}

export function useCreateWorkOrderStage(workOrderId: number) {
  const invalidate = useInvalidateStages(workOrderId)
  return useMutation<WorkOrderStage, AxiosError<ApiErrorResponse>, string>({
    mutationFn: (name) => createWorkOrderStage(workOrderId, name),
    onSuccess: invalidate,
  })
}

export function useRenameWorkOrderStage(workOrderId: number) {
  const invalidate = useInvalidateStages(workOrderId)
  return useMutation<WorkOrderStage, AxiosError<ApiErrorResponse>, { stageId: number; name: string }>({
    mutationFn: ({ stageId, name }) => renameWorkOrderStage(workOrderId, stageId, name),
    onSuccess: invalidate,
  })
}

export function useDeleteWorkOrderStage(workOrderId: number) {
  const invalidate = useInvalidateStages(workOrderId)
  return useMutation<void, AxiosError<ApiErrorResponse>, number>({
    mutationFn: (stageId) => deleteWorkOrderStage(workOrderId, stageId),
    onSuccess: invalidate,
  })
}

export function useReorderWorkOrderStages(workOrderId: number) {
  const invalidate = useInvalidateStages(workOrderId)
  return useMutation<WorkOrderStage[], AxiosError<ApiErrorResponse>, number[]>({
    mutationFn: (stageIds) => reorderWorkOrderStages(workOrderId, stageIds),
    onSuccess: invalidate,
  })
}

export function useCloseWorkOrderStage(workOrderId: number) {
  const invalidate = useInvalidateStages(workOrderId)
  return useMutation<WorkOrderStage, AxiosError<ApiErrorResponse>, number>({
    mutationFn: (stageId) => closeWorkOrderStage(workOrderId, stageId),
    onSuccess: invalidate,
  })
}

export function useReopenWorkOrderStage(workOrderId: number) {
  const invalidate = useInvalidateStages(workOrderId)
  return useMutation<WorkOrderStage, AxiosError<ApiErrorResponse>, number>({
    mutationFn: (stageId) => reopenWorkOrderStage(workOrderId, stageId),
    onSuccess: invalidate,
  })
}

/**
 * Drag-and-drop move (AC-013/AC-027): applies the reorder to the cached
 * board OPTIMISTICALLY (`applyBoardMove`) before the request lands, so the
 * drop renders instantly. A failure restores the pre-drag snapshot and shows
 * a toast; a success reconciles the two affected groups with the server's
 * own compact positions, in case a concurrent drag elsewhere raced this one.
 */
export function useMoveBoardTask(workOrderId: number) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()
  const boardKey = taskBoardKeys.board(workOrderId)

  return useMutation<
    MoveBoardTaskResult,
    AxiosError<ApiErrorResponse>,
    MoveBoardTaskPayload,
    TaskBoardPayload | undefined
  >({
    mutationFn: (payload) => moveBoardTask(workOrderId, payload),
    onMutate: async (payload) => {
      // Step 1: stop an in-flight refetch from clobbering the optimistic write, and snapshot for rollback
      await queryClient.cancelQueries({ queryKey: boardKey })
      const previous = queryClient.getQueryData<TaskBoardPayload>(boardKey)
      // Step 2: apply the move locally so the drop renders immediately
      if (previous) {
        queryClient.setQueryData<TaskBoardPayload>(boardKey, {
          ...previous,
          tasks: applyBoardMove(previous.tasks, payload),
        })
      }
      return previous
    },
    onError: (_error, _payload, previous) => {
      // Step 3: a failed move reverts to the pre-drag snapshot and surfaces a toast
      if (previous) {
        queryClient.setQueryData(boardKey, previous)
      }
      toast.error(t('workOrders.taskBoard.moveError'))
    },
    onSuccess: (result) => {
      // Step 4: reconcile the two affected groups with the server's own authoritative rows
      queryClient.setQueryData<TaskBoardPayload>(boardKey, (current) => {
        if (!current) {
          return current
        }
        const rowById = new Map(result.tasks.map((row) => [row.id, row]))
        return {
          ...current,
          tasks: current.tasks.map((task) => {
            const row = rowById.get(task.id)
            return row
              ? { ...task, work_order_stage_id: row.work_order_stage_id, stage_position: row.stage_position }
              : task
          }),
        }
      })
    },
  })
}

/**
 * Bulk actions (D-7): one request, one outcome per task in the response —
 * never a single all-or-nothing rejection. The caller renders `results`
 * itself (success/failure summary); this hook only invalidates the board so
 * the applied changes (status, assignees, dates...) come back on refetch.
 */
export function useBulkBoardTaskAction(workOrderId: number) {
  const invalidateBoard = useInvalidateTaskBoard(workOrderId)
  return useMutation<BulkTaskResult, AxiosError<ApiErrorResponse>, BulkTaskPayload>({
    mutationFn: (payload) => bulkBoardTaskAction(workOrderId, payload),
    onSuccess: invalidateBoard,
  })
}
