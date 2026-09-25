/**
 * Resolves a "per stato" Kanban drop into the right mutation (spec 0157 D-3),
 * reusing the SAME decision the grid's status-cell intercept already makes
 * (`resolveTaskStatusInterceptDecision`) so the two surfaces never disagree:
 * open<->open PATCHes `task_status_id` directly; a move INTO a closing status
 * opens "Completa" (the picked status id is never written directly — the
 * dialog derives the real destination server-side); a move OUT of a closed
 * status calls `uncomplete`. Cancelling the dialog applies NOTHING, so the
 * card falls back to its current column on the next render for free — there
 * is no local "pending move" state to revert.
 */
import { useCallback, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { fetchTask, updateTask, uncompleteTask } from '@/features/tasks/api'
import { actionErrorMessage } from '@/features/tasks/task-action-error-message'
import { resolveTaskStatusInterceptDecision } from '@/features/tasks/task-status-intercept-decision'
import type { TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'

type MoveState =
  | { kind: 'idle' }
  | { kind: 'loading' }
  | { kind: 'complete'; task: TaskDetailWithPermissions }

export interface UseTaskKanbanStatusMoveArgs {
  /** Called after a successful PATCH/uncomplete/"Completa" so the caller refetches the board. */
  onMutated: () => void
}

export function useTaskKanbanStatusMove({ onMutated }: UseTaskKanbanStatusMoveArgs) {
  const { t } = useTranslation()
  const [state, setState] = useState<MoveState>({ kind: 'idle' })

  const moveToStatus = useCallback(
    (row: TaskKanbanRow, targetStatusId: number, targetGroup: TaskStatusGroupValue) => {
      const decision = resolveTaskStatusInterceptDecision(row.task_status?.group, targetGroup)

      if (decision === 'open_complete') {
        setState({ kind: 'loading' })
        fetchTask(Number(row.id))
          .then((task) => setState({ kind: 'complete', task }))
          .catch((error: unknown) => {
            toast.error(actionErrorMessage(t, error))
            setState({ kind: 'idle' })
          })
        return
      }

      if (decision === 'uncomplete') {
        uncompleteTask(Number(row.id))
          .then(() => {
            toast.success(t('tasks.actions.uncomplete.success'))
            onMutated()
          })
          .catch((error: unknown) => toast.error(actionErrorMessage(t, error)))
        return
      }

      updateTask(Number(row.id), { task_status_id: targetStatusId })
        .then(onMutated)
        .catch((error: unknown) => toast.error(actionErrorMessage(t, error)))
    },
    [t, onMutated],
  )

  const closeCompleteDialog = useCallback(() => setState({ kind: 'idle' }), [])
  const handleCompleted = useCallback(() => {
    setState({ kind: 'idle' })
    onMutated()
  }, [onMutated])

  return {
    moveToStatus,
    completingTask: state.kind === 'complete' ? state.task : null,
    closeCompleteDialog,
    handleCompleted,
  }
}
