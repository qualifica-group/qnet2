/**
 * Bulk "Completa" (D-7): ONE `closure_feedback`/`time_entry` applied to every
 * selected task. Reuses the Task detail's own "Completa" segnatempo section
 * (`TaskCompleteTimeEntrySection` + `useTaskCompleteTimeEntryForm`) UNCHANGED
 * — same required fields, same validation — with `taskTypeId: null` since a
 * bulk selection has no single task type to default from (AC-039 then falls
 * back to the type picker's first option). A task requiring validation, or
 * blocked, fails that ROW instead of the whole request (AC-018): there is no
 * `validation_status_id` field here, that path stays single-task only.
 */

import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { TaskCompleteTimeEntrySection } from '@/features/tasks/task-complete-time-entry-section'
import { useTaskCompleteTimeEntryForm } from '@/features/tasks/use-task-complete-time-entry-form'
import { useBulkBoardTaskAction } from '@/features/work-orders/task-board/use-task-board-mutations'
import { showBulkResultToast } from '@/features/work-orders/task-board/task-board-bulk-summary-toast'

interface CompleteFormValues {
  closure_feedback: string
}

interface TaskBoardBulkCompleteDialogProps {
  workOrderId: number
  taskIds: number[]
  onClose: () => void
  onSuccess: () => void
}

export function TaskBoardBulkCompleteDialog({ workOrderId, taskIds, onClose, onSuccess }: TaskBoardBulkCompleteDialogProps) {
  const { t } = useTranslation()
  const form = useForm<CompleteFormValues>({ defaultValues: { closure_feedback: '' } })
  const timeEntryForm = useTaskCompleteTimeEntryForm({ taskTypeId: null })
  const mutation = useBulkBoardTaskAction(workOrderId)

  const onSubmit = async (values: CompleteFormValues) => {
    // AC-039 mirror: an invalid segnatempo blocks the submit before any request fires.
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
      showBulkResultToast(t, result)
      onClose()
      onSuccess()
    } catch (error) {
      // 409 (commessa chiusa) has no field to attach to (D-9); a 422 on `time_entry.*`
      // is wired inline by the reused section's own error mapper.
      if (axios.isAxiosError(error) && error.response?.status === 409) {
        toast.error(t('workOrders.taskBoard.bulk.genericError'))
        return
      }
      const timeEntryHandled = timeEntryForm.applyServerErrors(error)
      if (!timeEntryHandled) {
        toast.error(t('workOrders.taskBoard.bulk.genericError'))
      }
    }
  }

  return (
    <Dialog open onOpenChange={(next) => !next && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('workOrders.taskBoard.bulk.completeDialog.title')}</DialogTitle>
        </DialogHeader>

        <div className="max-h-[70vh] overflow-y-auto">
          <Form {...form}>
            <form
              id="task-board-bulk-complete-form"
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
          <Button type="submit" form="task-board-bulk-complete-form" disabled={mutation.isPending}>
            {t('workOrders.taskBoard.bulk.completeDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
