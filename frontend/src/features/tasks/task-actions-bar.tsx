import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { BadgeCheck, BellRing, CheckCircle2, Lock, RotateCcw, Unlock, XOctagon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'
import { ACTION_BUTTON_VARIANT } from '@/features/table/action-tone'
import { taskActionAvailability } from '@/features/tasks/task-action-availability'
import { actionErrorMessage } from '@/features/tasks/task-action-error-message'
import { useBlockTask, useUnblockTask, useUncompleteTask } from '@/features/tasks/use-task-mutations'
import { TaskCompleteDialog } from '@/features/tasks/task-complete-dialog'
import { TaskRequestUpdateDialog } from '@/features/tasks/task-request-update-dialog'
import { TaskValidationDialog } from '@/features/tasks/task-validation-dialog'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

/** Which action dialog (if any) is currently open. */
type OpenDialog = 'none' | 'complete' | 'approve' | 'reject' | 'request_update'

interface TaskActionsBarProps {
  task: TaskDetailWithPermissions
}

/**
 * Every domain action gated on the AND of the client-side AVAILABILITY rule
 * (`taskActionAvailability`, WHEN the action makes sense given the task's own
 * phase/`is_blocked`) and the server-computed `permissions.actions` flag (the
 * actual authorization) — mirrors `contract-actions-bar.tsx`
 * (`lifecycle.X && contract.permissions.actions.X`) verbatim (spec 0116 D-1).
 * A flag that is false means the button is absent from the DOM, never merely
 * disabled (AC-041).
 *
 * "Completa" and "Approva"/"Rifiuta" open a dialog (feedback/validation
 * target for the former, a plain confirmation for the latter two); "Riapri",
 * "Blocca" and "Sblocca" need no input and reuse the app's imperative
 * `useConfirm`, exactly as `ContractActionsBar` does for "Riapri contratto"
 * on the suspended path. "Richiedi aggiornamento" (spec 0118 D-10) opens its
 * own recipient-picker dialog, the only action whose matrix row also enables
 * the osservatore.
 */
export function TaskActionsBar({ task }: TaskActionsBarProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [openDialog, setOpenDialog] = useState<OpenDialog>('none')
  const availability = taskActionAvailability(task)

  const uncompleteMutation = useUncompleteTask({
    taskId: task.id,
    onSuccess: () => toast.success(t('tasks.actions.uncomplete.success')),
  })
  const blockMutation = useBlockTask({
    taskId: task.id,
    onSuccess: () => toast.success(t('tasks.actions.block.success')),
  })
  const unblockMutation = useUnblockTask({
    taskId: task.id,
    onSuccess: () => toast.success(t('tasks.actions.unblock.success')),
  })

  const handleUncomplete = async () => {
    const confirmed = await confirm({
      title: t('tasks.actions.uncomplete.label'),
      description: t('tasks.actions.uncomplete.confirmDescription'),
      confirmLabel: t('tasks.actions.uncomplete.confirm'),
    })
    if (!confirmed) {
      return
    }
    try {
      await uncompleteMutation.mutateAsync()
    } catch (error) {
      toast.error(actionErrorMessage(t, error))
    }
  }

  const handleBlock = async () => {
    const confirmed = await confirm({
      title: t('tasks.actions.block.label'),
      description: t('tasks.actions.block.confirmDescription'),
      confirmLabel: t('tasks.actions.block.confirm'),
      tone: 'destructive',
    })
    if (!confirmed) {
      return
    }
    try {
      await blockMutation.mutateAsync()
    } catch (error) {
      toast.error(actionErrorMessage(t, error))
    }
  }

  const handleUnblock = async () => {
    const confirmed = await confirm({
      title: t('tasks.actions.unblock.label'),
      description: t('tasks.actions.unblock.confirmDescription'),
      confirmLabel: t('tasks.actions.unblock.confirm'),
    })
    if (!confirmed) {
      return
    }
    try {
      await unblockMutation.mutateAsync()
    } catch (error) {
      toast.error(actionErrorMessage(t, error))
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-2 border-y bg-muted/40 px-4 py-3">
      {availability.complete && task.permissions.actions.complete ? (
        <Button type="button" variant={ACTION_BUTTON_VARIANT.success} size="sm" onClick={() => setOpenDialog('complete')}>
          <CheckCircle2 aria-hidden="true" />
          {t('tasks.actions.complete.label')}
        </Button>
      ) : null}

      {availability.uncomplete && task.permissions.actions.uncomplete ? (
        <Button
          type="button"
          variant={ACTION_BUTTON_VARIANT.action}
          className="bg-card"
          size="sm"
          onClick={() => void handleUncomplete()}
          disabled={uncompleteMutation.isPending}
        >
          <RotateCcw aria-hidden="true" />
          {t('tasks.actions.uncomplete.label')}
        </Button>
      ) : null}

      {availability.approve && task.permissions.actions.approve ? (
        <Button type="button" variant={ACTION_BUTTON_VARIANT.success} size="sm" onClick={() => setOpenDialog('approve')}>
          <BadgeCheck aria-hidden="true" />
          {t('tasks.actions.approve.label')}
        </Button>
      ) : null}

      {availability.reject && task.permissions.actions.reject ? (
        <Button type="button" variant={ACTION_BUTTON_VARIANT.danger} size="sm" onClick={() => setOpenDialog('reject')}>
          <XOctagon aria-hidden="true" />
          {t('tasks.actions.reject.label')}
        </Button>
      ) : null}

      {availability.block && task.permissions.actions.block ? (
        <Button
          type="button"
          variant={ACTION_BUTTON_VARIANT.danger}
          size="sm"
          onClick={() => void handleBlock()}
          disabled={blockMutation.isPending}
        >
          <Lock aria-hidden="true" />
          {t('tasks.actions.block.label')}
        </Button>
      ) : null}

      {availability.unblock && task.permissions.actions.unblock ? (
        <Button
          type="button"
          variant={ACTION_BUTTON_VARIANT.action}
          className="bg-card"
          size="sm"
          onClick={() => void handleUnblock()}
          disabled={unblockMutation.isPending}
        >
          <Unlock aria-hidden="true" />
          {t('tasks.actions.unblock.label')}
        </Button>
      ) : null}

      {availability.request_update && task.permissions.actions.request_update ? (
        <Button
          type="button"
          variant={ACTION_BUTTON_VARIANT.action}
          className="bg-card"
          size="sm"
          onClick={() => setOpenDialog('request_update')}
        >
          <BellRing aria-hidden="true" />
          {t('tasks.actions.requestUpdate.label')}
        </Button>
      ) : null}

      {/* Spec 0123 D-6/AC-023: `complete`/`complete_to_validation`/`approve` are already
          false whenever this is `> 0` — the reason the buttons above are missing. */}
      {task.open_subtasks_count > 0 ? (
        <p className="text-xs text-muted-foreground">
          {t('tasks.actions.openSubtasksBlocking', { count: task.open_subtasks_count })}
        </p>
      ) : null}

      <TaskCompleteDialog
        open={openDialog === 'complete'}
        onOpenChange={(open) => setOpenDialog(open ? 'complete' : 'none')}
        task={task}
        // Spec 0155 D-6: the task detail always completes for every assignee.
        forAllAssignees
      />
      <TaskValidationDialog
        open={openDialog === 'approve' || openDialog === 'reject'}
        onOpenChange={(open) => {
          if (!open) {
            setOpenDialog('none')
          }
        }}
        mode={openDialog === 'reject' ? 'reject' : 'approve'}
        task={task}
      />
      <TaskRequestUpdateDialog
        open={openDialog === 'request_update'}
        onOpenChange={(open) => setOpenDialog(open ? 'request_update' : 'none')}
        task={task}
      />
    </div>
  )
}
