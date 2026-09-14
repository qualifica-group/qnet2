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
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { TASK_STATUSES_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import { IN_VALIDATION_GROUP_PARAMS } from '@/features/tasks/task-action-availability'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import { useCompleteTask } from '@/features/tasks/use-task-mutations'
import type { CompleteTaskPayload, TaskDetailWithPermissions } from '@/features/tasks/types'

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

/** Omits `closure_feedback` when blank; omits `validation_status_id` entirely off the validation path (D-3). */
function buildCompletePayload(values: CompleteTaskFormValues, toValidation: boolean): CompleteTaskPayload {
  const payload: CompleteTaskPayload = {}
  const feedback = values.closure_feedback.trim()
  if (feedback !== '') {
    payload.closure_feedback = feedback
  }
  if (toValidation) {
    payload.validation_status_id = values.validation_status_id
  }
  return payload
}

interface TaskCompleteDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  task: TaskDetailWithPermissions
}

/**
 * "Completa" (spec 0121 D-2/D-3/D-6, RECTIFIES spec 0116 D-8): closes the
 * task, or — when the server's own `complete_to_validation` says so — sends
 * it to the `in_validation` status picked here instead. There is no switch:
 * the actor never chooses the path, only (on the validation path) its
 * destination. The button stays "Completa" either way (D-7); only the title
 * changes. The segnatempo the product document also wants in this pop-up is
 * OUT OF SCOPE (0116 D-10): nothing here prepares a field for it.
 */
export function TaskCompleteDialog({ open, onOpenChange, task }: TaskCompleteDialogProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const toValidation = task.permissions.actions.complete_to_validation
  const schema = buildCompleteTaskSchema(t, task.requires_closure_feedback, toValidation)

  const form = useForm<CompleteTaskFormValues>({
    resolver: zodResolver(schema),
    defaultValues: completeTaskDefaultValues(),
  })
  const closureFeedback = useWatch({ control: form.control, name: 'closure_feedback' })
  const validationStatusId = useWatch({ control: form.control, name: 'validation_status_id' })

  const completeMutation = useCompleteTask({
    taskId: task.id,
    onSuccess: () => {
      toast.success(t('tasks.actions.completeDialog.success'))
      onOpenChange(false)
      form.reset(completeTaskDefaultValues())
    },
  })

  const onSubmit = async (values: CompleteTaskFormValues) => {
    try {
      await completeMutation.mutateAsync(buildCompletePayload(values, toValidation))
    } catch (error) {
      // 409 (Task bloccato, AC-044) has no field to attach to: a dedicated
      // toast. A 422 on a known field (feedback/validation status) is wired
      // inline by `applyServerValidationErrors`; any other 422 (wrong phase,
      // `isCompletable`) falls back to the generic phase toast.
      if (axios.isAxiosError(error) && error.response?.status === 409) {
        toast.error(t('tasks.actions.errors.blocked'))
        return
      }
      if (applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        return
      }
      toast.error(t('tasks.actions.errors.generic'))
    }
  }

  // AC-042: the submit stays disabled while a required feedback is empty,
  // independent of RHF's own (debounced) validation pass.
  const feedbackMissing = task.requires_closure_feedback && closureFeedback.trim() === ''
  const validationStatusMissing = toValidation && validationStatusId === null
  const submitDisabled = completeMutation.isPending || feedbackMissing || validationStatusMissing

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          form.reset(completeTaskDefaultValues())
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
                <FormDescription>{t('tasks.actions.completeDialog.validationHint')}</FormDescription>
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
          </form>
        </Form>

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
