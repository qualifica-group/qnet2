import { useTranslation } from 'react-i18next'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
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
import { RelationSelectField } from '@/components/form/relation-select-field'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { TASK_STATUSES_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import { IN_VALIDATION_GROUP_PARAMS } from '@/features/tasks/task-action-availability'
import { TaskCompleteCascadeSection } from '@/features/tasks/task-complete-cascade-section'
import { TaskCompleteTimeEntryToggleSection } from '@/features/tasks/task-complete-time-entry-toggle'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import { useCompleteTask } from '@/features/tasks/use-task-mutations'
import { useTaskBulkTimeEntryShares } from '@/features/tasks/use-task-bulk-time-entry-shares'
import { handleTaskCompleteCascadeError, useTaskCompleteCascade } from '@/features/tasks/use-task-complete-cascade'
import { useTaskCompleteTimeEntryForm } from '@/features/tasks/use-task-complete-time-entry-form'
import {
  buildCompletePayload,
  buildCompleteTaskSchema,
  completeTaskDefaultValues,
  type CompleteTaskFormValues,
} from '@/features/tasks/task-complete-form'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

const SERVER_ERROR_FIELDS = ['closure_feedback', 'validation_status_id'] as const

interface TaskCompleteDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  task: TaskDetailWithPermissions
  /**
   * Fires after a successful completion, in addition to closing the dialog.
   * The sub-task panel uses it to refresh the PARENT's own cached detail —
   * `useCompleteTask` only seeds this task's own query.
   */
  onCompleted?: () => void
}

/**
 * "Completa" (spec 0121 D-2/D-3/D-6, RECTIFIES spec 0116 D-8): closes the
 * task, or — when the server's own `complete_to_validation` says so — sends
 * it to the `in_validation` status picked here instead. There is no switch:
 * the actor never chooses the path, only (on the validation path) its
 * destination. The button stays "Completa" either way (D-7); only the title
 * changes. The segnatempo is now MANDATORY on both paths (spec 0123 D-1): the
 * "Segnatempo" section below is a SEPARATE `useForm` (`use-task-complete-time-entry-form.ts`)
 * validated and submitted together with this one.
 *
 * Spec 0418: on a task with open sub-tasks the Completa closes them too
 * (`useTaskCompleteCascade`): the segnatempo becomes a TOTAL split between the
 * task and its open sub-tasks ("Ripartizione"), and the segnatempo or the
 * feedback is required when any of those tasks requires it.
 */
export function TaskCompleteDialog({
  open,
  onOpenChange,
  task,
  onCompleted,
}: TaskCompleteDialogProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const toValidation = task.permissions.actions.complete_to_validation
  const cascade = useTaskCompleteCascade(task, open)
  const schema = buildCompleteTaskSchema(t, cascade.requiresClosureFeedback, toValidation)

  const form = useForm<CompleteTaskFormValues>({
    resolver: zodResolver(schema),
    defaultValues: completeTaskDefaultValues(),
  })
  const closureFeedback = useWatch({ control: form.control, name: 'closure_feedback' })
  const timeEntryForm = useTaskCompleteTimeEntryForm({
    taskTypeId: task.task_type_id,
    requiresTimeEntry: cascade.requiresTimeEntry,
  })
  const shares = useTaskBulkTimeEntryShares(timeEntryForm, cascade.targets)

  const completeMutation = useCompleteTask({
    taskId: task.id,
    onSuccess: () => {
      toast.success(t('tasks.actions.completeDialog.success'))
      onOpenChange(false)
      form.reset(completeTaskDefaultValues())
      timeEntryForm.reset()
      onCompleted?.()
    },
  })

  const onSubmit = async (values: CompleteTaskFormValues) => {
    // AC-039: an invalid segnatempo (e.g. empty minutes) blocks the submit
    // with an inline field error and never reaches the API.
    const timeEntryPayload = await timeEntryForm.validate()
    if (timeEntryPayload === null) {
      return
    }
    if (cascade.active && timeEntryPayload !== undefined && !shares.isValid) {
      return
    }
    try {
      await completeMutation.mutateAsync(
        buildCompletePayload(values, toValidation, timeEntryPayload, cascade.active ? shares.shares : undefined),
      )
    } catch (error) {
      // 409 (Task bloccato, AC-044) has no field to attach to: a dedicated
      // toast. A 422 on a known field (feedback/validation status/time_entry.*)
      // is wired inline by `applyServerValidationErrors`/`timeEntryForm.applyServerErrors`;
      // any other 422 (wrong phase, `isCompletable`) falls back to the generic phase toast.
      if (axios.isAxiosError(error) && error.response?.status === 409) {
        toast.error(t('tasks.actions.errors.blocked'))
        return
      }
      if (handleTaskCompleteCascadeError(t, error, shares)) {
        return
      }
      const timeEntryHandled = timeEntryForm.applyServerErrors(error)
      const feedbackHandled = applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])
      if (timeEntryHandled || feedbackHandled) {
        return
      }
      toast.error(t('tasks.actions.errors.generic'))
    }
  }

  // AC-020: the submit stays PRE-EMPTIVELY disabled while a required feedback
  // is empty, independent of RHF's own (debounced) validation pass. AC-019 is
  // deliberately NOT mirrored here: a missing validation status leaves the
  // button clickable, so the submit attempt runs the schema and shows the
  // field error under the picker instead of silently doing nothing.
  const feedbackMissing = cascade.requiresClosureFeedback && closureFeedback.trim() === ''
  const submitDisabled = completeMutation.isPending || feedbackMissing || cascade.isLoading || cascade.isError

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          form.reset(completeTaskDefaultValues())
          timeEntryForm.reset()
        }
        onOpenChange(next)
      }}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>
            {toValidation ? t('tasks.actions.completeDialog.validationTitle') : t('tasks.actions.complete.label')}
          </DialogTitle>
          <DialogDescription>{t('tasks.actions.completeDialog.description')}</DialogDescription>
        </DialogHeader>

        <div className="max-h-[70vh] overflow-y-auto">
          <Form {...form}>
            <form
              id="task-complete-form"
              className="flex flex-col gap-4"
              onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
            >
              <FormField
                control={form.control}
                name="closure_feedback"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel required={cascade.requiresClosureFeedback}>
                      {t('tasks.actions.completeDialog.feedback')}
                    </FormLabel>
                    <FormControl>
                      <Textarea
                        rows={3}
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

              {toValidation ? (
                <>
                  <p className="text-sm text-muted-foreground">
                    {t('tasks.actions.completeDialog.validationHint')}
                  </p>
                  <RelationSelectField
                    control={form.control}
                    name="validation_status_id"
                    metaKey="validation_status_id"
                    label={t('tasks.actions.completeDialog.validationStatus')}
                    resource={TASK_STATUSES_FOR_SELECT_RESOURCE}
                    searchPlaceholder={t('tasks.form.statusSearch')}
                    params={IN_VALIDATION_GROUP_PARAMS}
                    selected={null}
                    required
                    {...selectLabels}
                  />
                </>
              ) : null}

              <TaskCompleteTimeEntryToggleSection timeEntryForm={timeEntryForm} disabled={completeMutation.isPending} />

              {cascade.active ? (
                <TaskCompleteCascadeSection
                  cascade={cascade}
                  shares={shares}
                  showShares={timeEntryForm.enabled}
                  disabled={completeMutation.isPending}
                />
              ) : null}

              {timeEntryForm.enabled ? (
                <FormField
                  control={form.control}
                  name="for_all_assignees"
                  render={({ field }) => (
                    <FormItem className="flex flex-row items-center gap-2">
                      <FormControl>
                        <Checkbox
                          checked={field.value}
                          onCheckedChange={(checked) => field.onChange(checked === true)}
                          disabled={completeMutation.isPending}
                        />
                      </FormControl>
                      <FormLabel className="font-normal">{t('tasks.actions.completeDialog.forAllAssignees')}</FormLabel>
                    </FormItem>
                  )}
                />
              ) : null}
            </form>
          </Form>
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" form="task-complete-form" disabled={submitDisabled}>
            {completeMutation.isPending
              ? t('tasks.actions.completeDialog.saving')
              : t('tasks.actions.completeDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
