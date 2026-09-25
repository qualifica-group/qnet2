import { useTranslation } from 'react-i18next'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import axios from 'axios'
import type { TFunction } from 'i18next'
import { Button } from '@/components/ui/button'
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
import { TaskCompleteTimeEntryToggleSection } from '@/features/tasks/task-complete-time-entry-toggle'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import { useCompleteTask } from '@/features/tasks/use-task-mutations'
import { useTaskCompleteTimeEntryForm } from '@/features/tasks/use-task-complete-time-entry-form'
import type { CompleteTaskPayload, CompleteTaskTimeEntryPayload, TaskDetailWithPermissions } from '@/features/tasks/types'

const SERVER_ERROR_FIELDS = ['closure_feedback', 'validation_status_id'] as const

/**
 * Spec 0121 RECTIFIES spec 0116 D-8/AC-043: the completion path is no longer a
 * client choice, so there is no `request_validation` switch to read any more
 * — `toValidation` (`permissions.actions.complete_to_validation`, D-6) is a
 * prop carrying the SERVER's own verdict. The closure feedback stays optional
 * unless the task requires one (D-4, unchanged shape); the validation-status
 * pick is required exactly when the server sent the task down that path.
 */
function buildCompleteTaskSchema(t: TFunction, requiresFeedback: boolean, toValidation: boolean) {
  return z
    .object({
      closure_feedback: z.string(),
      validation_status_id: z.number().nullable(),
    })
    .superRefine((values, ctx) => {
      if (requiresFeedback && values.closure_feedback.trim() === '') {
        ctx.addIssue({
          code: 'custom',
          path: ['closure_feedback'],
          message: t('tasks.actions.completeDialog.feedbackRequired'),
        })
      }
      if (toValidation && values.validation_status_id === null) {
        ctx.addIssue({
          code: 'custom',
          path: ['validation_status_id'],
          message: t('tasks.actions.completeDialog.validationStatusRequired'),
        })
      }
    })
}

type CompleteTaskFormValues = z.infer<ReturnType<typeof buildCompleteTaskSchema>>

function completeTaskDefaultValues(): CompleteTaskFormValues {
  return { closure_feedback: '', validation_status_id: null }
}

/**
 * Omits `closure_feedback` when blank; omits `validation_status_id` entirely
 * off the validation path (D-3). `time_entry` is present whenever
 * `useTaskCompleteTimeEntryForm.validate()` returns a payload — always, on a
 * task that requires it (spec 0123 D-1); `undefined` (so the key is dropped
 * at the wire boundary) when the task's own segnatempo is optional and the
 * "Registra il tempo" switch is off (spec 0162 D-3). `for_all_assignees`
 * (spec 0155 D-6) is sent only when `true` — the server default (`false`)
 * already covers the sub-task panel's own case.
 */
function buildCompletePayload(
  values: CompleteTaskFormValues,
  toValidation: boolean,
  timeEntry: CompleteTaskTimeEntryPayload | undefined,
  forAllAssignees: boolean,
): CompleteTaskPayload {
  const payload: CompleteTaskPayload = { time_entry: timeEntry }
  const feedback = values.closure_feedback.trim()
  if (feedback !== '') {
    payload.closure_feedback = feedback
  }
  if (toValidation) {
    payload.validation_status_id = values.validation_status_id
  }
  if (forAllAssignees) {
    payload.for_all_assignees = true
  }
  return payload
}

interface TaskCompleteDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  task: TaskDetailWithPermissions
  /**
   * Spec 0155 D-6: `true` from the task detail (and list); `false` from the
   * sub-task panel and the kanban — q-net's own behaviour, no user-facing
   * toggle. Required (no default) so every call site states its own intent.
   */
  forAllAssignees: boolean
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
 */
export function TaskCompleteDialog({
  open,
  onOpenChange,
  task,
  forAllAssignees,
  onCompleted,
}: TaskCompleteDialogProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const toValidation = task.permissions.actions.complete_to_validation
  const schema = buildCompleteTaskSchema(t, task.requires_closure_feedback, toValidation)

  const form = useForm<CompleteTaskFormValues>({
    resolver: zodResolver(schema),
    defaultValues: completeTaskDefaultValues(),
  })
  const closureFeedback = useWatch({ control: form.control, name: 'closure_feedback' })
  const timeEntryForm = useTaskCompleteTimeEntryForm({
    taskTypeId: task.task_type_id,
    requiresTimeEntry: task.requires_time_entry,
  })

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
    try {
      await completeMutation.mutateAsync(
        buildCompletePayload(values, toValidation, timeEntryPayload, forAllAssignees),
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
  const feedbackMissing = task.requires_closure_feedback && closureFeedback.trim() === ''
  const submitDisabled = completeMutation.isPending || feedbackMissing

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
                    <FormLabel required={task.requires_closure_feedback}>
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
