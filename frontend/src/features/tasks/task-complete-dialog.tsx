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
import { Switch } from '@/components/ui/switch'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { TASK_STATUSES_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import { IN_VALIDATION_GROUP_PARAMS } from '@/features/tasks/task-action-availability'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import { useCompleteTask } from '@/features/tasks/use-task-mutations'
import type { CompleteTaskPayload, TaskDetailWithPermissions } from '@/features/tasks/types'

const SERVER_ERROR_FIELDS = ['closure_feedback', 'validation_status_id'] as const

/**
 * CASO 1 (chiusura) always accepts an OPTIONAL feedback unless the task
 * requires one (D-7, replicated for UX exactly as `task-schema.ts` does for
 * the form); CASO 2 (richiedi validazione, D-8) additionally requires the
 * destination `in_validation` status. Neither branch is chosen by the SCHEMA
 * — `buildCompletePayload` reads `request_validation` to decide which keys
 * reach the server, so an unrequested validation never sends the id (D-4).
 */
function buildCompleteTaskSchema(t: TFunction, requiresFeedback: boolean) {
  return z
    .object({
      closure_feedback: z.string(),
      request_validation: z.boolean(),
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
      if (values.request_validation && values.validation_status_id === null) {
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
  return { closure_feedback: '', request_validation: false, validation_status_id: null }
}

/** CASO 2 omits `closure_feedback` when blank; CASO 1 omits `validation_status_id` entirely (AC-043). */
function buildCompletePayload(values: CompleteTaskFormValues): CompleteTaskPayload {
  const payload: CompleteTaskPayload = {}
  const feedback = values.closure_feedback.trim()
  if (feedback !== '') {
    payload.closure_feedback = feedback
  }
  if (values.request_validation) {
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
 * "Completa" (spec 0116 D-8): closes the task, or — when "richiedi
 * validazione" is on — sends it to the `in_validation` status picked here
 * instead (AC-043). The segnatempo the product document also wants in this
 * pop-up is OUT OF SCOPE (D-10): nothing here prepares a field for it.
 */
export function TaskCompleteDialog({ open, onOpenChange, task }: TaskCompleteDialogProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const schema = buildCompleteTaskSchema(t, task.requires_closure_feedback)

  const form = useForm<CompleteTaskFormValues>({
    resolver: zodResolver(schema),
    defaultValues: completeTaskDefaultValues(),
  })
  const closureFeedback = useWatch({ control: form.control, name: 'closure_feedback' })
  const requestValidation = useWatch({ control: form.control, name: 'request_validation' })
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
      await completeMutation.mutateAsync(buildCompletePayload(values))
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
  const validationStatusMissing = requestValidation && validationStatusId === null
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
          <DialogTitle>{t('tasks.actions.complete.label')}</DialogTitle>
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

            <FormField
              control={form.control}
              name="request_validation"
              render={({ field }) => (
                <FormItem>
                  <div className="flex items-center justify-between gap-3">
                    <FormLabel>{t('tasks.actions.completeDialog.requestValidation')}</FormLabel>
                    <FormControl>
                      <Switch checked={field.value} onCheckedChange={field.onChange} />
                    </FormControl>
                  </div>
                </FormItem>
              )}
            />

            {requestValidation ? (
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
