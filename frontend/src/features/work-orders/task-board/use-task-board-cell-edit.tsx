/**
 * The list view's in-place editing (spec 0401 D-3/D-4/D-5), built from the
 * SAME pieces the Task grid uses: the generic cell PATCH (so every server
 * guard of TaskCellWriter applies), the grid's status-commit interceptor and
 * its "Completa" row action. Every write refreshes the board and the
 * commessa detail.
 */

import { useMemo, type ReactNode } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { updateTableCell } from '@/features/table/api'
import type { TableRow } from '@/features/table/types'
import { TASKS_DOMAIN, uncompleteTask } from '@/features/tasks/api'
import { actionErrorMessage } from '@/features/tasks/task-action-error-message'
import { useTaskDomainRowActions } from '@/features/tasks/use-task-domain-row-actions'
import { useTaskStatusCellIntercept } from '@/features/tasks/use-task-status-cell-intercept'
import type { TaskBoardCellEdit, TaskBoardCellValue, TaskBoardEditableColumn } from '@/features/work-orders/task-board/task-board-row-columns'
import { useInvalidateTaskBoard } from '@/features/work-orders/task-board/use-task-board-mutations'
import type { BoardTask } from '@/features/work-orders/task-board/types'

interface CellPatch {
  taskId: number
  column: TaskBoardEditableColumn
  value: TaskBoardCellValue
}

/** The grid helpers read only the row id. */
function asTableRow(task: BoardTask): TableRow {
  return { id: task.id, actions: [] }
}

export function useTaskBoardCellEdit(workOrderId: number): { cellEdit: TaskBoardCellEdit; dialogSlot: ReactNode } {
  const { t } = useTranslation()
  const invalidateBoard = useInvalidateTaskBoard(workOrderId)

  const { completeRow, dialogSlot: completeDialogSlot } = useTaskDomainRowActions({ onMutated: invalidateBoard })
  const { interceptCommit, dialogSlot: statusDialogSlot } = useTaskStatusCellIntercept({ onMutated: invalidateBoard })

  const { mutate: patchCell } = useMutation({
    mutationFn: ({ taskId, column, value }: CellPatch) => updateTableCell(TASKS_DOMAIN, taskId, { column, value }),
    onSuccess: invalidateBoard,
    onError: (error: unknown) => toast.error(apiErrorMessage(error, t('table.cellUpdateError'), i18n)),
  })

  const cellEdit = useMemo<TaskBoardCellEdit>(
    () => ({
      commit: (task, column, value) => {
        // Step 1: a status that closes or reopens the task goes through its own flow (D-5)
        const isIntercepted =
          column === 'task_status' &&
          interceptCommit({ columnId: column, row: asTableRow(task), oldValue: task.task_status, newValue: { id: value } })
        if (isIntercepted) {
          return
        }
        // Step 2: every other edit is the grid's own cell PATCH (D-4)
        patchCell({ taskId: task.id, column, value })
      },
      complete: (task) => completeRow(asTableRow(task)),
      reopen: (task) => {
        uncompleteTask(task.id)
          .then(() => {
            toast.success(t('tasks.actions.uncomplete.success'))
            void invalidateBoard()
          })
          .catch((error: unknown) => toast.error(actionErrorMessage(t, error)))
      },
    }),
    [completeRow, interceptCommit, invalidateBoard, patchCell, t],
  )

  return {
    cellEdit,
    dialogSlot: (
      <>
        {completeDialogSlot}
        {statusDialogSlot}
      </>
    ),
  }
}
