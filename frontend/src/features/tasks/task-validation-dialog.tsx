import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useApproveTask, useRejectTask } from '@/features/tasks/use-task-mutations'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

export type TaskValidationMode = 'approve' | 'reject'

interface TaskValidationDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  mode: TaskValidationMode
  task: TaskDetailWithPermissions
}

/**
 * "Approva validazione"/"Rifiuta validazione" (spec 0116 D-8): no field, only
 * the confirmation — the rejection does NOT clear `closure_feedback` (it is
 * the motivation the validator decided on), so the copy never promises a
 * clearing that does not happen.
 */
export function TaskValidationDialog({ open, onOpenChange, mode, task }: TaskValidationDialogProps) {
  const { t } = useTranslation()
  const isApprove = mode === 'approve'

  const approveMutation = useApproveTask({
    taskId: task.id,
    onSuccess: () => {
      toast.success(t('tasks.actions.approve.success'))
      onOpenChange(false)
    },
  })
  const rejectMutation = useRejectTask({
    taskId: task.id,
    onSuccess: () => {
      toast.success(t('tasks.actions.reject.success'))
      onOpenChange(false)
    },
  })
  const mutation = isApprove ? approveMutation : rejectMutation

  const handleConfirm = async () => {
    try {
      await mutation.mutateAsync()
    } catch (error) {
      const status = axios.isAxiosError(error) ? error.response?.status : undefined
      if (status === 409) {
        toast.error(t('tasks.actions.errors.blocked'))
      } else if (status === 422) {
        toast.error(t('tasks.actions.errors.wrongPhase'))
      } else {
        toast.error(t('tasks.actions.errors.generic'))
      }
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t(isApprove ? 'tasks.actions.approve.label' : 'tasks.actions.reject.label')}</DialogTitle>
          <DialogDescription>
            {t(isApprove ? 'tasks.actions.approveDialog.description' : 'tasks.actions.rejectDialog.description')}
          </DialogDescription>
        </DialogHeader>

        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            variant={isApprove ? 'success' : 'destructive'}
            onClick={() => void handleConfirm()}
            disabled={mutation.isPending}
          >
            {mutation.isPending
              ? t('tasks.actions.validationDialog.saving')
              : t(isApprove ? 'tasks.actions.approveDialog.confirm' : 'tasks.actions.rejectDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
