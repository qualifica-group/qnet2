/**
 * The "..." menu of a phase header (D-4): rename (handed back to the header,
 * which owns the inline input), close or reopen, delete behind a confirm.
 * Split out of `task-board-stage-group.tsx` so the header stays a layout.
 */

import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { AxiosError } from 'axios'
import { Lock, LockOpen, MoreHorizontal, Pencil, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { ApiErrorResponse } from '@/api/types'
import {
  useCloseWorkOrderStage,
  useDeleteWorkOrderStage,
  useReopenWorkOrderStage,
} from '@/features/work-orders/task-board/use-task-board-mutations'
import type { CloseStageConflictError, WorkOrderStage } from '@/features/work-orders/task-board/types'

function openTasksCountFromError(error: AxiosError<ApiErrorResponse>): number | undefined {
  const body = error.response?.data as CloseStageConflictError | undefined
  return body?.data?.open_tasks_count
}

interface TaskBoardStageMenuProps {
  workOrderId: number
  stage: WorkOrderStage
  onRename: () => void
}

export function TaskBoardStageMenu({ workOrderId, stage, onRename }: TaskBoardStageMenuProps) {
  const { t } = useTranslation()
  const [isConfirmingDelete, setIsConfirmingDelete] = useState(false)
  const closeStage = useCloseWorkOrderStage(workOrderId)
  const reopenStage = useReopenWorkOrderStage(workOrderId)
  const deleteStage = useDeleteWorkOrderStage(workOrderId)
  const isClosed = stage.closed_at !== null

  const handleClose = () =>
    closeStage.mutate(stage.id, {
      onError: (error) => {
        const openCount = openTasksCountFromError(error)
        toast.error(
          openCount !== undefined
            ? t('workOrders.taskBoard.stage.closeErrorOpenTasks', { count: openCount })
            : t('workOrders.taskBoard.stage.genericError'),
        )
      },
    })

  const handleDelete = () =>
    deleteStage.mutate(stage.id, {
      onSuccess: () => setIsConfirmingDelete(false),
      onError: () => toast.error(t('workOrders.taskBoard.stage.genericError')),
    })

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button size="icon-xs" variant="ghost" className="shrink-0" aria-label={t('workOrders.taskBoard.stage.menuLabel')}>
            <MoreHorizontal aria-hidden="true" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
          align="end"
          // Radix returns focus to the trigger once the menu closes; without this,
          // that restore races the rename `Input`'s own `autoFocus`, blurs it right
          // after mount and fires the rename submit before the user typed anything.
          onCloseAutoFocus={(event) => event.preventDefault()}
        >
          <DropdownMenuItem onSelect={onRename}>
            <Pencil aria-hidden="true" />
            {t('workOrders.taskBoard.stage.rename')}
          </DropdownMenuItem>
          {isClosed ? (
            <DropdownMenuItem onSelect={() => reopenStage.mutate(stage.id)}>
              <LockOpen aria-hidden="true" />
              {t('workOrders.taskBoard.stage.reopen')}
            </DropdownMenuItem>
          ) : (
            <DropdownMenuItem onSelect={handleClose}>
              <Lock aria-hidden="true" />
              {t('workOrders.taskBoard.stage.close')}
            </DropdownMenuItem>
          )}
          <DropdownMenuSeparator />
          <DropdownMenuItem variant="destructive" onSelect={() => setIsConfirmingDelete(true)}>
            <Trash2 aria-hidden="true" />
            {t('workOrders.taskBoard.stage.remove')}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <AlertDialog open={isConfirmingDelete} onOpenChange={setIsConfirmingDelete}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('workOrders.taskBoard.stage.removeConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{t('workOrders.taskBoard.stage.removeConfirmDescription')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              onClick={(event) => {
                event.preventDefault()
                handleDelete()
              }}
            >
              {t('workOrders.taskBoard.stage.remove')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
