/**
 * The six domain-action row keys of spec 0156 D-5 (complete, uncomplete,
 * approve, reject, block, unblock, request_update — same catalog/behavior as
 * the task detail's own `TaskActionsBar`). `complete`/`approve`/`reject`/
 * `request_update` open the SAME dialogs the detail uses, fed by a fresh
 * `GET /tasks/{id}` (the row's own grid projection carries none of the
 * fields those dialogs need); `uncomplete`/`block`/`unblock` need no dialog
 * and run directly, mirroring `TaskActionsBar`'s own handlers.
 */
import { useCallback, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { blockTask, fetchTask, uncompleteTask, unblockTask } from '@/features/tasks/api'
import { actionErrorMessage } from '@/features/tasks/task-action-error-message'
import { TaskCompleteDialog } from '@/features/tasks/task-complete-dialog'
import { TaskRequestUpdateDialog } from '@/features/tasks/task-request-update-dialog'
import { TaskValidationDialog } from '@/features/tasks/task-validation-dialog'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

type FetchedDialogKind = 'complete' | 'approve' | 'reject' | 'request_update'

type DialogState =
  | { kind: 'none' }
  | { kind: 'loading' }
  | { kind: FetchedDialogKind; task: TaskDetailWithPermissions }

export interface UseTaskDomainRowActionsOptions {
  /** Called after any of the six actions actually changes something, so the caller refreshes the grid. */
  onMutated: () => void
}

export interface UseTaskDomainRowActionsResult {
  handleAction: RowActionHandler
  dialogSlot: ReactNode
}

export function useTaskDomainRowActions({
  onMutated,
}: UseTaskDomainRowActionsOptions): UseTaskDomainRowActionsResult {
  const { t } = useTranslation()
  const [dialog, setDialog] = useState<DialogState>({ kind: 'none' })

  const closeDialog = useCallback(() => setDialog({ kind: 'none' }), [])

  const openFetchedDialog = useCallback(
    (kind: FetchedDialogKind, rowId: number) => {
      setDialog({ kind: 'loading' })
      fetchTask(rowId)
        .then((task) => setDialog({ kind, task }))
        .catch((error: unknown) => {
          toast.error(actionErrorMessage(t, error))
          setDialog({ kind: 'none' })
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

  const runBlock = useCallback(
    (rowId: number) => {
      blockTask(rowId)
        .then(() => {
          toast.success(t('tasks.actions.block.success'))
          onMutated()
        })
        .catch((error: unknown) => toast.error(actionErrorMessage(t, error)))
    },
    [t, onMutated],
  )

  const runUnblock = useCallback(
    (rowId: number) => {
      unblockTask(rowId)
        .then(() => {
          toast.success(t('tasks.actions.unblock.success'))
          onMutated()
        })
        .catch((error: unknown) => toast.error(actionErrorMessage(t, error)))
    },
    [t, onMutated],
  )

  const handleAction: RowActionHandler = useCallback(
    (action, row) => {
      const rowId = Number(row.id)
      switch (action.key) {
        case 'complete':
        case 'approve':
        case 'reject':
        case 'request_update':
          openFetchedDialog(action.key, rowId)
          break
        case 'uncomplete':
          runUncomplete(rowId)
          break
        case 'block':
          runBlock(rowId)
          break
        case 'unblock':
          runUnblock(rowId)
          break
        default:
          break
      }
    },
    [openFetchedDialog, runUncomplete, runBlock, runUnblock],
  )

  // Every fetched dialog's own success path already calls `onOpenChange(false)`
  // internally (mirrors `TaskActionsBar`): closing IS the signal to refresh —
  // a plain cancel refreshes too, a harmless no-op extra fetch, not a bug.
  const handleDialogOpenChange = (open: boolean) => {
    if (!open) {
      closeDialog()
      onMutated()
    }
  }

  const dialogSlot: ReactNode =
    dialog.kind === 'complete' ? (
      <TaskCompleteDialog open onOpenChange={handleDialogOpenChange} task={dialog.task} forAllAssignees />
    ) : dialog.kind === 'approve' || dialog.kind === 'reject' ? (
      <TaskValidationDialog open onOpenChange={handleDialogOpenChange} mode={dialog.kind} task={dialog.task} />
    ) : dialog.kind === 'request_update' ? (
      <TaskRequestUpdateDialog open onOpenChange={handleDialogOpenChange} task={dialog.task} />
    ) : null

  return { handleAction, dialogSlot }
}
