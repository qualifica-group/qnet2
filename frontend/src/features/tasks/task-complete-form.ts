/**
 * Form shape of the single Completa pop-up (`task-complete-dialog.tsx`): its
 * Zod schema, defaults and wire payload, split out of the dialog to keep it
 * within the file-size budget.
 */
import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { CompleteTaskPayload, CompleteTaskTimeEntryPayload } from '@/features/tasks/types'

/**
 * Spec 0121 RECTIFIES spec 0116 D-8/AC-043: the completion path is no longer a
 * client choice, so there is no `request_validation` switch to read any more
 * — `toValidation` (`permissions.actions.complete_to_validation`, D-6) is a
 * prop carrying the SERVER's own verdict. The closure feedback stays optional
 * unless the task requires one (D-4, unchanged shape); the validation-status
 * pick is required exactly when the server sent the task down that path.
 */
export function buildCompleteTaskSchema(t: TFunction, requiresFeedback: boolean, toValidation: boolean) {
  return z
    .object({
      closure_feedback: z.string(),
      validation_status_id: z.number().nullable(),
      for_all_assignees: z.boolean(),
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

export type CompleteTaskFormValues = z.infer<ReturnType<typeof buildCompleteTaskSchema>>

export function completeTaskDefaultValues(): CompleteTaskFormValues {
  return { closure_feedback: '', validation_status_id: null, for_all_assignees: true }
}

/**
 * Omits `closure_feedback` when blank; omits `validation_status_id` entirely
 * off the validation path (D-3). `time_entry` is present whenever
 * `useTaskCompleteTimeEntryForm.validate()` returns a payload — always, on a
 * task that requires it (spec 0123 D-1); `undefined` (so the key is dropped
 * at the wire boundary) when the task's own segnatempo is optional and the
 * "Registra il tempo" switch is off (spec 0162 D-3). `for_all_assignees`
 * (spec 0205, RECTIFIES spec 0155 D-6) is always sent: the actor's own
 * checkbox choice, checked by default.
 */
export function buildCompletePayload(
  values: CompleteTaskFormValues,
  toValidation: boolean,
  timeEntry: CompleteTaskTimeEntryPayload | undefined,
  shares: CompleteTaskPayload['time_entry_shares'],
): CompleteTaskPayload {
  const payload: CompleteTaskPayload = { time_entry: timeEntry, for_all_assignees: values.for_all_assignees }
  if (timeEntry !== undefined && shares !== undefined) {
    payload.time_entry_shares = shares
  }
  const feedback = values.closure_feedback.trim()
  if (feedback !== '') {
    payload.closure_feedback = feedback
  }
  if (toValidation) {
    payload.validation_status_id = values.validation_status_id
  }
  return payload
}
