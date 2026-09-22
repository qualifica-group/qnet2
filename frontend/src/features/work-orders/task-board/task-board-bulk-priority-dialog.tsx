/**
 * Bulk "Cambia priorita'" (D-7): one `task_priority_id` applied to every
 * selected task. Reuses the same async lookup picker + colored badge the
 * task form's own "Priorita'" field renders (`AsyncPaginatedSelect` +
 * `TASK_PRIORITIES_FOR_SELECT_RESOURCE` + `TaskLookupBadge`), wired directly
 * (no `RelationSelectField`/`MetaField`: there is no single record's field
 * permissions to read here, only the action-level authorization the server
 * checks per task in the bulk response).
 */

import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { toast } from 'sonner'
import type { TFunction } from 'i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { TASK_PRIORITIES_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import { useBulkBoardTaskAction } from '@/features/work-orders/task-board/use-task-board-mutations'
import { showBulkResultToast } from '@/features/work-orders/task-board/task-board-bulk-summary-toast'
import type { ForSelectItem } from '@/features/for-select/types'

function priorityBadge(item: ForSelectItem) {
  const meta = (item as ForSelectItem & { meta?: { color?: string; icon?: string | null } }).meta
  return <TaskLookupBadge value={{ id: item.id, name: item.label, color: meta?.color ?? '', icon: meta?.icon ?? null }} />
}

function buildSchema(t: TFunction) {
  return z.object({ task_priority_id: z.number().nullable() }).superRefine((values, ctx) => {
    if (values.task_priority_id === null) {
      ctx.addIssue({
        code: 'custom',
        path: ['task_priority_id'],
        message: t('workOrders.taskBoard.bulk.priorityDialog.priorityRequired'),
      })
    }
  })
}

type PriorityFormValues = { task_priority_id: number | null }

interface TaskBoardBulkPriorityDialogProps {
  workOrderId: number
  taskIds: number[]
  onClose: () => void
  onSuccess: () => void
}

export function TaskBoardBulkPriorityDialog({ workOrderId, taskIds, onClose, onSuccess }: TaskBoardBulkPriorityDialogProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const form = useForm<PriorityFormValues>({
    resolver: zodResolver(buildSchema(t)),
    defaultValues: { task_priority_id: null },
  })
  const mutation = useBulkBoardTaskAction(workOrderId)

  const onSubmit = async (values: PriorityFormValues) => {
    try {
      const result = await mutation.mutateAsync({
        action: 'priority',
        task_ids: taskIds,
        task_priority_id: values.task_priority_id as number,
      })
      showBulkResultToast(t, result)
      onClose()
      onSuccess()
    } catch (error) {
      const handled = applyServerValidationErrors(error, form.setError, ['task_priority_id'])
      if (!handled) {
        toast.error(t('workOrders.taskBoard.bulk.genericError'))
      }
    }
  }

  return (
    <Dialog open onOpenChange={(next) => !next && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('workOrders.taskBoard.bulk.priorityDialog.title')}</DialogTitle>
        </DialogHeader>

        <Form {...form}>
          <form id="task-board-bulk-priority-form" onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}>
            <FormField
              control={form.control}
              name="task_priority_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('tasks.form.priority')}</FormLabel>
                  <FormControl>
                    <AsyncPaginatedSelect
                      resource={TASK_PRIORITIES_FOR_SELECT_RESOURCE}
                      value={field.value}
                      onChange={field.onChange}
                      disabled={mutation.isPending}
                      renderItem={priorityBadge}
                      labels={{
                        placeholder: selectLabels.placeholder,
                        searchPlaceholder: t('tasks.form.prioritySearch'),
                        empty: selectLabels.emptyLabel,
                        error: selectLabels.errorLabel,
                        clearLabel: selectLabels.clearLabel,
                        triggerLabel: t('tasks.form.priority'),
                        retry: selectLabels.retryLabel,
                      }}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </form>
        </Form>

        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" form="task-board-bulk-priority-form" disabled={mutation.isPending}>
            {t('workOrders.taskBoard.bulk.priorityDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
