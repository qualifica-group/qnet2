/**
 * Builds the tasks list's `getBulkActions` (spec 0156 D-6): the eight actions
 * `POST /api/tasks/bulk` accepts, gated client-side on the SAME base
 * permission the endpoint re-checks server-side per contract
 * (`assign`/`priority`/`start_date`/`end_date` -> `tasks.update`,
 * `complete`/`uncomplete` -> `tasks.complete`, `block`/`unblock` ->
 * `tasks.block`, `delete` -> `tasks.delete`). Four actions open a dialog
 * (assign/complete/priority/one of the two dates); the other four are a
 * plain `useConfirm` + a direct request, mirroring `TaskActionsBar`'s own
 * uncomplete/block/unblock handlers.
 */
import { useCallback, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { CalendarRange, CheckCircle2, Flag, Lock, RotateCcw, Trash2, Unlock, Users } from 'lucide-react'
import { useConfirm, type ConfirmOptions } from '@/components/confirm-dialog-context'
import { useAbilities } from '@/features/auth/use-abilities'
import { taskBulkErrorDescription } from '@/features/tasks/task-bulk-error'
import { TaskBulkAssignDialog } from '@/features/tasks/task-bulk-assign-dialog'
import { TaskBulkCompleteDialog } from '@/features/tasks/task-bulk-complete-dialog'
import { TaskBulkDateDialog } from '@/features/tasks/task-bulk-date-dialog'
import { TaskBulkPriorityDialog } from '@/features/tasks/task-bulk-priority-dialog'
import { useTaskBulkMutation } from '@/features/tasks/use-task-bulk-mutation'
import type { TaskBulkAction } from '@/features/tasks/task-bulk-types'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { TableRow } from '@/features/table/types'

type ActiveDialog =
  | { kind: 'none' }
  | { kind: 'assign' | 'priority' | 'start_date' | 'end_date'; ids: number[] }
  /** Spec 0162 D-4: carries the selected `rows` too, `TaskBulkCompleteDialog` reads `requires_time_entry` off them. */
  | { kind: 'complete'; ids: number[]; rows: TableRow[] }

interface UseTaskBulkActionsSlotArgs {
  /** Purges and reloads the grid after any bulk action succeeds. */
  refresh: () => void
  /** Empties the grid's row selection after any bulk action succeeds. */
  clearSelection: () => void
}

interface UseTaskBulkActionsSlotResult {
  getBulkActions: (selection: TableSelection) => BulkAction[]
  dialogSlot: ReactNode
}

export function useTaskBulkActionsSlot({
  refresh,
  clearSelection,
}: UseTaskBulkActionsSlotArgs): UseTaskBulkActionsSlotResult {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const confirm = useConfirm()
  const mutation = useTaskBulkMutation()
  const [dialog, setDialog] = useState<ActiveDialog>({ kind: 'none' })

  const handleSuccess = useCallback(() => {
    setDialog({ kind: 'none' })
    refresh()
    clearSelection()
  }, [refresh, clearSelection])

  // Step 1: confirm (when the action carries a confirmation), Step 2: submit,
  // Step 3: report success/failure — shared by every body-less action.
  const runSimpleAction = useCallback(
    async (action: TaskBulkAction, ids: number[], confirmOptions: ConfirmOptions) => {
      const confirmed = await confirm(confirmOptions)
      if (!confirmed) {
        return
      }
      try {
        const result = await mutation.mutateAsync({ action, task_ids: ids })
        toast.success(t('tasks.bulk.success', { count: result.affected }))
        handleSuccess()
      } catch (error) {
        const { message, reasons } = taskBulkErrorDescription(t, error)
        toast.error(message, reasons.length > 0 ? { description: reasons.join(' ') } : undefined)
      }
    },
    [confirm, mutation, t, handleSuccess],
  )

  const getBulkActions = useCallback(
    (selection: TableSelection): BulkAction[] => {
      const ids = selection.ids.filter((id): id is number => typeof id === 'number')
      if (ids.length === 0) {
        return []
      }

      const items: BulkAction[] = []

      if (can('tasks.update')) {
        items.push({
          key: 'assign',
          label: t('tasks.bulk.assign'),
          icon: Users,
          onSelect: () => setDialog({ kind: 'assign', ids }),
        })
      }

      if (can('tasks.complete')) {
        items.push({
          key: 'complete',
          label: t('tasks.bulk.complete'),
          icon: CheckCircle2,
          onSelect: () => setDialog({ kind: 'complete', ids, rows: selection.rows }),
        })
        items.push({
          key: 'uncomplete',
          label: t('tasks.bulk.uncomplete'),
          icon: RotateCcw,
          onSelect: () =>
            void runSimpleAction('uncomplete', ids, {
              title: t('tasks.bulk.uncomplete'),
              description: t('tasks.bulk.uncompleteConfirmDescription', { count: ids.length }),
              confirmLabel: t('tasks.bulk.uncomplete'),
            }),
        })
      }

      if (can('tasks.block')) {
        items.push({
          key: 'block',
          label: t('tasks.bulk.block'),
          icon: Lock,
          onSelect: () =>
            void runSimpleAction('block', ids, {
              title: t('tasks.bulk.block'),
              description: t('tasks.bulk.blockConfirmDescription', { count: ids.length }),
              confirmLabel: t('tasks.bulk.block'),
              tone: 'destructive',
            }),
        })
        items.push({
          key: 'unblock',
          label: t('tasks.bulk.unblock'),
          icon: Unlock,
          onSelect: () =>
            void runSimpleAction('unblock', ids, {
              title: t('tasks.bulk.unblock'),
              description: t('tasks.bulk.unblockConfirmDescription', { count: ids.length }),
              confirmLabel: t('tasks.bulk.unblock'),
            }),
        })
      }

      if (can('tasks.update')) {
        items.push({
          key: 'priority',
          label: t('tasks.bulk.priority'),
          icon: Flag,
          onSelect: () => setDialog({ kind: 'priority', ids }),
        })
        items.push({
          key: 'start_date',
          label: t('tasks.bulk.startDate'),
          icon: CalendarRange,
          onSelect: () => setDialog({ kind: 'start_date', ids }),
        })
        items.push({
          key: 'end_date',
          label: t('tasks.bulk.endDate'),
          icon: CalendarRange,
          onSelect: () => setDialog({ kind: 'end_date', ids }),
        })
      }

      if (can('tasks.delete')) {
        items.push({
          key: 'delete',
          label: t('tasks.bulk.delete'),
          icon: Trash2,
          destructive: true,
          onSelect: () =>
            void runSimpleAction('delete', ids, {
              title: t('tasks.bulk.delete'),
              description: t('tasks.bulk.deleteConfirmDescription', { count: ids.length }),
              confirmLabel: t('tasks.bulk.delete'),
              tone: 'destructive',
            }),
        })
      }

      return items
    },
    [can, t, runSimpleAction],
  )

  const closeDialog = () => setDialog({ kind: 'none' })

  const dialogSlot =
    dialog.kind === 'assign' ? (
      <TaskBulkAssignDialog taskIds={dialog.ids} onClose={closeDialog} onSuccess={handleSuccess} />
    ) : dialog.kind === 'complete' ? (
      <TaskBulkCompleteDialog taskIds={dialog.ids} rows={dialog.rows} onClose={closeDialog} onSuccess={handleSuccess} />
    ) : dialog.kind === 'priority' ? (
      <TaskBulkPriorityDialog taskIds={dialog.ids} onClose={closeDialog} onSuccess={handleSuccess} />
    ) : dialog.kind === 'start_date' || dialog.kind === 'end_date' ? (
      <TaskBulkDateDialog action={dialog.kind} taskIds={dialog.ids} onClose={closeDialog} onSuccess={handleSuccess} />
    ) : null

  return { getBulkActions, dialogSlot }
}
