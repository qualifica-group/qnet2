/**
 * Bulk "Completa" (D-7): ONE `closure_feedback` applied to every selected task.
 * Reuses the Task detail's own "Completa" segnatempo section
 * (`TaskCompleteTimeEntrySection` + `useTaskCompleteTimeEntryForm`) UNCHANGED
 * — same required fields, same validation — with `taskTypeId: null` since a
 * bulk selection has no single task type to default from (AC-039 then falls
 * back to the type picker's first option). There is no `validation_status_id`
 * field here: a task requiring validation stays a single-task action.
 *
 * Spec 0338: all-or-nothing (D-5, 422 `incompatible_tasks` keeps the pop-up
 * open with a per-task reasons toast); the segnatempo is a TOTAL split among
 * the tasks by the "Ripartizione" section.
 *
 * `for_all_assignees` (spec 0319): same checkbox as `TaskCompleteDialog`, but
 * UNCHECKED by default here — the board logs the segnatempo for the actor
 * only unless they opt in.
 */

import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { taskBulkErrorDescription, taskBulkIncompatibleTasks } from '@/features/tasks/task-bulk-error'
import { TaskBulkTimeEntrySharesSection } from '@/features/tasks/task-bulk-time-entry-shares-section'
import { useTaskBulkTimeEntryShares, type TaskShareTarget } from '@/features/tasks/use-task-bulk-time-entry-shares'
import { TaskCompleteTimeEntrySection } from '@/features/tasks/task-complete-time-entry-section'
import { useTaskCompleteTimeEntryForm } from '@/features/tasks/use-task-complete-time-entry-form'
import { useBulkBoardTaskAction } from '@/features/work-orders/task-board/use-task-board-mutations'
import { showBulkResultToast } from '@/features/work-orders/task-board/task-board-bulk-summary-toast'

interface CompleteFormValues {
  closure_feedback: string
  for_all_assignees: boolean
}

const DEFAULT_VALUES: CompleteFormValues = { closure_feedback: '', for_all_assignees: false }

interface TaskBoardBulkCompleteDialogProps {
  workOrderId: number
  /** The selected tasks in selection order: ids for the request, titles for the "Ripartizione" rows. */
  tasks: TaskShareTarget[]
  onClose: () => void
  onSuccess: () => void
}

export function TaskBoardBulkCompleteDialog({ workOrderId, tasks, onClose, onSuccess }: TaskBoardBulkCompleteDialogProps) {
  const { t } = useTranslation()
  const form = useForm<CompleteFormValues>({ defaultValues: DEFAULT_VALUES })
  const timeEntryForm = useTaskCompleteTimeEntryForm({ taskTypeId: null })
  const shares = useTaskBulkTimeEntryShares(timeEntryForm, tasks)
  const mutation = useBulkBoardTaskAction(workOrderId)
  const taskIds = tasks.map((task) => task.id)

  const onSubmit = async (values: CompleteFormValues) => {
    // AC-039 mirror: an invalid segnatempo blocks the submit before any request fires.
    // The board's own bulk complete never passes `requiresTimeEntry: false` (spec
    // 0162 D-3/D-4 is task-list/detail only), so `validate()` never actually
    // resolves `undefined` here — the check still narrows the type for TS.
    const timeEntryPayload = await timeEntryForm.validate()
    if (timeEntryPayload === null || timeEntryPayload === undefined) {
      return
    }
    if (!shares.isValid) {
      return
    }
    try {
      const feedback = values.closure_feedback.trim()
      const result = await mutation.mutateAsync({
        action: 'complete',
        task_ids: taskIds,
        closure_feedback: feedback === '' ? undefined : feedback,
        time_entry: timeEntryPayload,
        time_entry_shares: shares.shares,
        for_all_assignees: values.for_all_assignees,
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
      if (taskBulkIncompatibleTasks(error) !== null) {
        const { message, reasons } = taskBulkErrorDescription(t, error)
        toast.error(message, { description: reasons.join(' ') })
        return
      }
      const timeEntryHandled = timeEntryForm.applyServerErrors(error)
      if (!shares.applyServerError(error) && !timeEntryHandled) {
        toast.error(t('workOrders.taskBoard.bulk.genericError'))
      }
    }
  }

  return (
    <Dialog open onOpenChange={(next) => !next && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('workOrders.taskBoard.bulk.completeDialog.title')}</DialogTitle>
          <DialogDescription>{t('tasks.bulk.completeDialog.description', { count: taskIds.length })}</DialogDescription>
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

              <TaskBulkTimeEntrySharesSection tasks={tasks} state={shares} disabled={mutation.isPending} />

              <FormField
                control={form.control}
                name="for_all_assignees"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center gap-2">
                    <FormControl>
                      <Checkbox
                        checked={field.value}
                        onCheckedChange={(checked) => field.onChange(checked === true)}
                        disabled={mutation.isPending}
                      />
                    </FormControl>
                    <FormLabel className="font-normal">{t('tasks.actions.completeDialog.forAllAssignees')}</FormLabel>
                  </FormItem>
                )}
              />
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
