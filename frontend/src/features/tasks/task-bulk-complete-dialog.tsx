/**
 * Bulk "Completa" (spec 0156 D-6): ONE `closure_feedback`/`time_entry`
 * applied to every selected task, all-or-nothing (a task that would need
 * validation or is blocked makes the WHOLE request 422 with
 * `incompatible_tasks`, unlike the task board's own per-row bulk complete).
 * Reuses the Task detail's segnatempo section unchanged, `taskTypeId: null`
 * since a bulk selection has no single type to default from.
 */
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { TaskCompleteTimeEntrySection } from '@/features/tasks/task-complete-time-entry-section'
import { taskBulkErrorDescription } from '@/features/tasks/task-bulk-error'
import { useTaskCompleteTimeEntryForm } from '@/features/tasks/use-task-complete-time-entry-form'
import { useTaskBulkMutation } from '@/features/tasks/use-task-bulk-mutation'

interface CompleteFormValues {
  closure_feedback: string
}

interface TaskBulkCompleteDialogProps {
  taskIds: number[]
  onClose: () => void
  onSuccess: (affected: number) => void
}

export function TaskBulkCompleteDialog({ taskIds, onClose, onSuccess }: TaskBulkCompleteDialogProps) {
  const { t } = useTranslation()
  const form = useForm<CompleteFormValues>({ defaultValues: { closure_feedback: '' } })
  const timeEntryForm = useTaskCompleteTimeEntryForm({ taskTypeId: null })
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

              <Form {...timeEntryForm.form}>
                <TaskCompleteTimeEntrySection
                  control={timeEntryForm.form.control}
                  disabled={mutation.isPending}
                  onStartTimeChange={timeEntryForm.handleStartTimeChange}
                  onEndTimeChange={timeEntryForm.handleEndTimeChange}
                />
              </Form>
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
