/**
 * Bulk "Riapri" and "Blocca" (D-7): both carry NO body of their own — only
 * the task selection (`BulkUncompletePayload`/`BulkBlockPayload`, `types.ts`)
 * — so a single `AlertDialog` confirmation covers both, parametrized by
 * `action`/i18n keys, rather than two near-identical files.
 */

import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { useBulkBoardTaskAction } from '@/features/work-orders/task-board/use-task-board-mutations'
import { showBulkResultToast } from '@/features/work-orders/task-board/task-board-bulk-summary-toast'

type ConfirmableAction = 'uncomplete' | 'block'

interface TaskBoardBulkConfirmDialogProps {
  action: ConfirmableAction
  workOrderId: number
  taskIds: number[]
  onClose: () => void
  onSuccess: () => void
}

export function TaskBoardBulkConfirmDialog({ action, workOrderId, taskIds, onClose, onSuccess }: TaskBoardBulkConfirmDialogProps) {
  const { t } = useTranslation()
  const mutation = useBulkBoardTaskAction(workOrderId)
  const dialogKey = action === 'uncomplete' ? 'uncompleteDialog' : 'blockDialog'

  const handleConfirm = async () => {
    try {
      const result = await mutation.mutateAsync({ action, task_ids: taskIds })
      showBulkResultToast(t, result)
      onClose()
      onSuccess()
    } catch {
      toast.error(t('workOrders.taskBoard.bulk.genericError'))
    }
  }

  return (
    <AlertDialog open onOpenChange={(next) => !next && !mutation.isPending && onClose()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t(`workOrders.taskBoard.bulk.${dialogKey}.title`)}</AlertDialogTitle>
          <AlertDialogDescription>
            {t('workOrders.taskBoard.bulk.selectedCount', { count: taskIds.length })}
          </AlertDialogDescription>
        </AlertDialogHeader>

        <AlertDialogFooter>
          <AlertDialogCancel disabled={mutation.isPending}>{t('common.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            disabled={mutation.isPending}
            onClick={(event) => {
              event.preventDefault()
              void handleConfirm()
            }}
          >
            {t(`workOrders.taskBoard.bulk.${dialogKey}.confirm`)}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
