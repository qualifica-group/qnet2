/**
 * "Carica modello task" dialog of the Task board header menu (spec 0399): picks
 * an active Modello di Task and replaces the tasks of the current one with its
 * tasks. The server refuses (409) when one of them is already completed; the
 * toast then names how many.
 */

import { useState } from 'react'
import { isAxiosError } from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { TASK_TEMPLATE_POPUP_WIDTH_CLASS, TASK_TEMPLATES_FOR_SELECT_RESOURCE } from '@/features/task-templates/for-select-api'
import type { TaskTemplateReloadConflictError } from '@/features/work-orders/task-board/types'
import { useReloadWorkOrderTaskTemplate } from '@/features/work-orders/task-board/use-task-board-mutations'

const SELECT_ID = 'task-board-template-reload-select'

function completedTasksCountFromError(error: unknown): number | undefined {
  if (!isAxiosError<TaskTemplateReloadConflictError>(error)) {
    return undefined
  }
  return error.response?.data?.data?.completed_tasks_count
}

interface TaskBoardTemplateReloadDialogProps {
  workOrderId: number
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function TaskBoardTemplateReloadDialog({ workOrderId, open, onOpenChange }: TaskBoardTemplateReloadDialogProps) {
  const { t } = useTranslation()
  const [taskTemplateId, setTaskTemplateId] = useState<number | null>(null)
  const reload = useReloadWorkOrderTaskTemplate(workOrderId)

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      setTaskTemplateId(null)
    }
    onOpenChange(next)
  }

  const handleConfirm = async () => {
    if (taskTemplateId === null) {
      return
    }

    try {
      await reload.mutateAsync(taskTemplateId)
      toast.success(t('workOrders.taskBoard.templateReload.success'))
      handleOpenChange(false)
    } catch (error) {
      const completedCount = completedTasksCountFromError(error)
      toast.error(
        completedCount === undefined
          ? t('workOrders.taskBoard.templateReload.genericError')
          : t('workOrders.taskBoard.templateReload.blocked', { count: completedCount }),
      )
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t('workOrders.taskBoard.templateReload.title')}</DialogTitle>
          <DialogDescription>{t('workOrders.taskBoard.templateReload.description')}</DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-1.5">
          <label htmlFor={SELECT_ID} className="text-sm font-medium">
            {t('workOrders.form.taskTemplateId')}
          </label>
          <AsyncPaginatedSelect
            id={SELECT_ID}
            resource={TASK_TEMPLATES_FOR_SELECT_RESOURCE}
            value={taskTemplateId}
            onChange={setTaskTemplateId}
            disabled={reload.isPending}
            contentClassName={TASK_TEMPLATE_POPUP_WIDTH_CLASS}
            labels={{
              placeholder: t('workOrders.form.taskTemplatePlaceholder'),
              searchPlaceholder: t('workOrders.form.taskTemplateSearchPlaceholder'),
              empty: t('workOrders.form.taskTemplateEmpty'),
              error: t('workOrders.form.taskTemplateError'),
              clearLabel: t('workOrders.form.taskTemplateClear'),
              triggerLabel: t('workOrders.form.taskTemplateId'),
              retry: t('common.retry'),
            }}
          />
        </div>

        <DialogFooter>
          <Button variant="outline" size="sm" onClick={() => handleOpenChange(false)} disabled={reload.isPending}>
            {t('common.cancel')}
          </Button>
          <Button size="sm" onClick={() => void handleConfirm()} disabled={taskTemplateId === null || reload.isPending}>
            {t('workOrders.taskBoard.templateReload.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
