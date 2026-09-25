/**
 * Bulk "Data inizio"/"Data fine" (spec 0156 D-6): ONE `date` (`Y-m-d`) applied
 * to every selected task — the frozen contract carries a SINGLE `date` field
 * per action, unlike the task board's own bulk dialog, which sets both dates
 * in one request. `action` picks which of the two this instance submits.
 */
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { toast } from 'sonner'
import type { TFunction } from 'i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { taskBulkErrorDescription, taskBulkIncompatibleTasks } from '@/features/tasks/task-bulk-error'
import { useTaskBulkMutation } from '@/features/tasks/use-task-bulk-mutation'

function buildSchema(t: TFunction) {
  return z.object({ date: z.string().min(1, t('tasks.bulk.dateDialog.dateRequired')) })
}

type DateFormValues = { date: string }

interface TaskBulkDateDialogProps {
  action: 'start_date' | 'end_date'
  taskIds: number[]
  onClose: () => void
  onSuccess: (affected: number) => void
}

export function TaskBulkDateDialog({ action, taskIds, onClose, onSuccess }: TaskBulkDateDialogProps) {
  const { t } = useTranslation()
  const form = useForm<DateFormValues>({
    resolver: zodResolver(buildSchema(t)),
    defaultValues: { date: '' },
  })
  const mutation = useTaskBulkMutation()
  const fieldLabel = t(action === 'start_date' ? 'tasks.form.startDate' : 'tasks.form.endDate')

  const onSubmit = async (values: DateFormValues) => {
    try {
      const result = await mutation.mutateAsync({ action, task_ids: taskIds, date: values.date })
      toast.success(t('tasks.bulk.success', { count: result.affected }))
      onClose()
      onSuccess(result.affected)
    } catch (error) {
      // `incompatible_tasks` (a rejected TASK, e.g. one outside the parent's
      // date range) and a field-scoped `errors.date` (a malformed REQUEST,
      // e.g. a bad date format) come from two disjoint response shapes
      // (`TaskBulkIncompatibleException` vs `BulkTaskRequest::rules()`) —
      // checked first, so `applyServerValidationErrors`'s "any 422 counts as
      // handled" never swallows the incompatible-tasks toast (BUG found by
      // `task-bulk-date-dialog.test.tsx`: it did, silently, before this check).
      if (!taskBulkIncompatibleTasks(error)) {
        const handled = applyServerValidationErrors(error, form.setError, ['date'])
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
          <DialogTitle>{t('tasks.bulk.dateDialog.title', { field: fieldLabel, count: taskIds.length })}</DialogTitle>
        </DialogHeader>

        <Form {...form}>
          <form id="task-bulk-date-form" onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}>
            <FormField
              control={form.control}
              name="date"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{fieldLabel}</FormLabel>
                  <FormControl>
                    <Input
                      type="date"
                      disabled={mutation.isPending}
                      value={field.value}
                      onChange={field.onChange}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
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
          <Button type="submit" form="task-bulk-date-form" disabled={mutation.isPending}>
            {t('tasks.bulk.dateDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
