/**
 * Bulk "Priorità" (spec 0156 D-6): one `task_priority_id` applied to every
 * selected task. Mirrors the task board's own `TaskBoardBulkPriorityDialog`
 * (same picker/badge), bound to `POST /api/tasks/bulk` instead.
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
import { taskLookupOptionRenderer } from '@/features/tasks/task-lookup-option-renderer'
import { TASK_PRIORITIES_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import { taskBulkErrorDescription, taskBulkIncompatibleTasks } from '@/features/tasks/task-bulk-error'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import { useTaskBulkMutation } from '@/features/tasks/use-task-bulk-mutation'

function buildSchema(t: TFunction) {
  return z.object({ task_priority_id: z.number().nullable() }).superRefine((values, ctx) => {
    if (values.task_priority_id === null) {
      ctx.addIssue({
        code: 'custom',
        path: ['task_priority_id'],
        message: t('tasks.bulk.priorityDialog.priorityRequired'),
      })
    }
  })
}

type PriorityFormValues = { task_priority_id: number | null }

interface TaskBulkPriorityDialogProps {
  taskIds: number[]
  onClose: () => void
  onSuccess: (affected: number) => void
}

export function TaskBulkPriorityDialog({ taskIds, onClose, onSuccess }: TaskBulkPriorityDialogProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const form = useForm<PriorityFormValues>({
    resolver: zodResolver(buildSchema(t)),
    defaultValues: { task_priority_id: null },
  })
  const mutation = useTaskBulkMutation()

  const onSubmit = async (values: PriorityFormValues) => {
    try {
      const result = await mutation.mutateAsync({
        action: 'priority',
        task_ids: taskIds,
        task_priority_id: values.task_priority_id as number,
      })
      toast.success(t('tasks.bulk.success', { count: result.affected }))
      onClose()
      onSuccess(result.affected)
    } catch (error) {
      // See `task-bulk-date-dialog.tsx`'s own comment: `incompatible_tasks`
      // and a field-scoped `errors.task_priority_id` come from two disjoint
      // response shapes, checked first so a rejected task's toast is never
      // swallowed by `applyServerValidationErrors`'s "any 422 = handled".
      if (!taskBulkIncompatibleTasks(error)) {
        const handled = applyServerValidationErrors(error, form.setError, ['task_priority_id'])
        if (handled) {
          return
        }
      }
      const { message, reasons } = taskBulkErrorDescription(t, error)
      toast.error(message, reasons.length > 0 ? { description: reasons.join(' ') } : undefined)
    }
  }

  return (
    <Dialog open onOpenChange={(next) => !next && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('tasks.bulk.priorityDialog.title', { count: taskIds.length })}</DialogTitle>
        </DialogHeader>

        <Form {...form}>
          <form id="task-bulk-priority-form" onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}>
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
                      renderItem={taskLookupOptionRenderer}
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
          <Button type="submit" form="task-bulk-priority-form" disabled={mutation.isPending}>
            {t('tasks.bulk.priorityDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
