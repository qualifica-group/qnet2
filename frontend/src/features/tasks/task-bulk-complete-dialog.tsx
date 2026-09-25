/**
 * Bulk "Completa" (spec 0156 D-6): ONE `closure_feedback`/`time_entry`
 * applied to every selected task, all-or-nothing (a task that would need
 * validation or is blocked makes the WHOLE request 422 with
 * `incompatible_tasks`, unlike the task board's own per-row bulk complete).
 * Reuses the Task detail's segnatempo section unchanged, `taskTypeId: null`
 * since a bulk selection has no single type to default from.
 *
 * Spec 0162 D-4: the "Registra il tempo" switch renders only when NONE of the
 * selected rows requires the segnatempo — read off `rows` (the grid's own
 * selection, `requires_time_entry` per row), never decided here. A row
 * missing the flag is treated as requiring it (conservative fallback).
 */
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { TaskCompleteTimeEntryToggleSection } from '@/features/tasks/task-complete-time-entry-toggle'
import { taskBulkErrorDescription } from '@/features/tasks/task-bulk-error'
import { useTaskCompleteTimeEntryForm } from '@/features/tasks/use-task-complete-time-entry-form'
import { useTaskBulkMutation } from '@/features/tasks/use-task-bulk-mutation'
import type { TableRow } from '@/features/table/types'

interface CompleteFormValues {
  closure_feedback: string
}

interface TaskBulkCompleteDialogProps {
  taskIds: number[]
  /** The selected rows themselves (spec 0162 D-4), same order/length as `taskIds`. */
  rows: TableRow[]
  onClose: () => void
  onSuccess: (affected: number) => void
}

/** Spec 0162 D-4: `false` only when EVERY selected row's own `requires_time_entry` is `false`. */
function anySelectedTaskRequiresTimeEntry(rows: TableRow[]): boolean {
  return rows.some((row) => row.requires_time_entry !== false)
}

export function TaskBulkCompleteDialog({ taskIds, rows, onClose, onSuccess }: TaskBulkCompleteDialogProps) {
  const { t } = useTranslation()
  const form = useForm<CompleteFormValues>({ defaultValues: { closure_feedback: '' } })
  const timeEntryForm = useTaskCompleteTimeEntryForm({
    taskTypeId: null,
    requiresTimeEntry: anySelectedTaskRequiresTimeEntry(rows),
  })
  const mutation = useTaskBulkMutation()

  const onSubmit = async (values: CompleteFormValues) => {
    const timeEntryPayload = await timeEntryForm.validate()
    if (timeEntryPayload === null) {
      return
    }
    try {
      const feedback = values.closure_feedback.trim()
      const result = await mutation.mutateAsync({
        action: 'complete',
        task_ids: taskIds,
        closure_feedback: feedback === '' ? undefined : feedback,
        time_entry: timeEntryPayload,
      })
      toast.success(t('tasks.bulk.success', { count: result.affected }))
      onClose()
      onSuccess(result.affected)
    } catch (error) {
      const timeEntryHandled = timeEntryForm.applyServerErrors(error)
      if (timeEntryHandled) {
        return
      }
      const { message, reasons } = taskBulkErrorDescription(t, error)
      toast.error(message, reasons.length > 0 ? { description: reasons.join(' ') } : undefined)
    }
  }

  return (
    <Dialog open onOpenChange={(next) => !next && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('tasks.bulk.completeDialog.title', { count: taskIds.length })}</DialogTitle>
        </DialogHeader>

        <div className="max-h-[70vh] overflow-y-auto">
          <Form {...form}>
            <form
              id="task-bulk-complete-form"
              className="flex flex-col gap-4"
              onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
            >
              <FormField
                control={form.control}
                name="closure_feedback"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('tasks.actions.completeDialog.feedback')}</FormLabel>
                    <FormControl>
                      <Textarea
                        rows={3}
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

              <TaskCompleteTimeEntryToggleSection timeEntryForm={timeEntryForm} disabled={mutation.isPending} />
            </form>
          </Form>
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" form="task-bulk-complete-form" disabled={mutation.isPending}>
            {t('tasks.bulk.completeDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
