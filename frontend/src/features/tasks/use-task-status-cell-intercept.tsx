/**
 * Intercepts a `task_status` cell commit in the Task list (spec 0156 D-8)
 * BEFORE it reaches the generic PATCH: picking a status that CLOSES the task
 * opens the same "Completa" dialog the detail/row action use (segnatempo and,
 * when needed, the validation destination — the picked status id itself is
 * never written directly, `TaskCompleteDialog` derives the real destination
 * server-side); picking back out of a closed status calls `uncomplete`
 * directly. Every other transition (open<->open, or a super-admin editing an
 * already-closed row) is NOT intercepted and PATCHes as any other column.
 */
import { useCallback, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { fetchTask, uncompleteTask } from '@/features/tasks/api'
import { actionErrorMessage } from '@/features/tasks/task-action-error-message'
import { TaskCompleteDialog } from '@/features/tasks/task-complete-dialog'
import { resolveTaskStatusInterceptDecision } from '@/features/tasks/task-status-intercept-decision'
import { useTaskStatusGroupLookup } from '@/features/tasks/use-task-status-group-lookup'
import type { CellCommitInterceptor } from '@/features/table/use-table-cell-edit'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'

type InterceptState =
  | { kind: 'idle' }
  | { kind: 'loading' }
  | { kind: 'complete'; task: TaskDetailWithPermissions }

export interface UseTaskStatusCellInterceptArgs {
  /** Called after a successful uncomplete (direct) or "Completa" (dialog) so the caller refreshes the grid. */
  onMutated: () => void
}

export function useTaskStatusCellIntercept({ onMutated }: UseTaskStatusCellInterceptArgs) {
  const { t } = useTranslation()
  const groups = useTaskStatusGroupLookup()
  const [state, setState] = useState<InterceptState>({ kind: 'idle' })

  const loadAndOpenComplete = useCallback(
    (rowId: number) => {
      setState({ kind: 'loading' })
      fetchTask(rowId)
        .then((task) => setState({ kind: 'complete', task }))
        .catch((error: unknown) => {
          toast.error(actionErrorMessage(t, error))
          setState({ kind: 'idle' })
        })
    },
    [t],
  )

  const runUncomplete = useCallback(
    (rowId: number) => {
      uncompleteTask(rowId)
        .then(() => {
          toast.success(t('tasks.actions.uncomplete.success'))
          onMutated()
        })
        .catch((error: unknown) => toast.error(actionErrorMessage(t, error)))
    },
    [t, onMutated],
  )

  const interceptCommit: CellCommitInterceptor = useCallback(
    ({ columnId, row, oldValue, newValue }) => {
      if (columnId !== 'task_status') {
        return false
      }
      const newId = (newValue as { id?: number } | null)?.id
      if (newId === undefined) {
        return false
      }
      const oldGroup = (oldValue as { group?: TaskStatusGroupValue } | null)?.group
      const decision = resolveTaskStatusInterceptDecision(oldGroup, groups.get(newId))

      if (decision === 'open_complete') {
        loadAndOpenComplete(Number(row.id))
        return true
      }
      if (decision === 'uncomplete') {
        runUncomplete(Number(row.id))
        return true
      }
      return false
    },
    [groups, loadAndOpenComplete, runUncomplete],
  )

  const dialogSlot =
    state.kind === 'complete' ? (
      <TaskCompleteDialog
        open
        onOpenChange={(open) => {
          if (!open) {
            setState({ kind: 'idle' })
          }
        }}
        task={state.task}
        forAllAssignees
        onCompleted={() => {
          setState({ kind: 'idle' })
          onMutated()
        }}
      />
    ) : null

  return { interceptCommit, dialogSlot }
}
