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
 *
 * Spec 0164 D-3: the caller decides WHICH columns a successful move
 * invalidates (origin/destination only), so `moveToStatus` takes its own
 * `onMutated` per call instead of a single one fixed at hook-mount time —
 * the "Completa" dialog's own async completion retains it in `MoveState`
 * until `handleCompleted`/`closeCompleteDialog` settles it.
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
  | { kind: 'complete'; task: TaskDetailWithPermissions; onMutated: () => void }

export function useTaskKanbanStatusMove() {
  const { t } = useTranslation()
  const [state, setState] = useState<MoveState>({ kind: 'idle' })

  const moveToStatus = useCallback(
    (row: TaskKanbanRow, targetStatusId: number, targetGroup: TaskStatusGroupValue, onMutated: () => void) => {
      const decision = resolveTaskStatusInterceptDecision(row.task_status?.group, targetGroup)

      if (decision === 'open_complete') {
        setState({ kind: 'loading' })
        fetchTask(Number(row.id))
          .then((task) => setState({ kind: 'complete', task, onMutated }))
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
    [t],
  )

  const closeCompleteDialog = useCallback(() => setState({ kind: 'idle' }), [])
  const handleCompleted = useCallback(() => {
    setState((current) => {
      if (current.kind === 'complete') {
        current.onMutated()
      }
      return { kind: 'idle' }
    })
  }, [])

  return {
    moveToStatus,
    completingTask: state.kind === 'complete' ? state.task : null,
    closeCompleteDialog,
    handleCompleted,
  }
}
