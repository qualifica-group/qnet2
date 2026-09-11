import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
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
import { Checkbox } from '@/components/ui/checkbox'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { actionErrorMessage } from '@/features/tasks/task-action-error-message'
import { useRequestTaskUpdate } from '@/features/tasks/use-task-mutations'
import type { RequestTaskUpdatePayload, TaskDetailWithPermissions, TaskNamedRef } from '@/features/tasks/types'

/** Backend `message` column limit (spec 0118 data_contract: `max:2000`). */
const MESSAGE_MAX_LENGTH = 2000

function buildRequestUpdateSchema(t: TFunction) {
  return z.object({
    recipient_ids: z.array(z.number()).min(1, t('tasks.actions.requestUpdate.recipientsRequired')),
    message: z.string(),
  })
}

type RequestUpdateFormValues = z.infer<ReturnType<typeof buildRequestUpdateSchema>>

function requestUpdateDefaultValues(): RequestUpdateFormValues {
  return { recipient_ids: [], message: '' }
}

/** Omits `message` when blank (D-12): the server's own default copy takes over. */
function buildRequestUpdatePayload(values: RequestUpdateFormValues): RequestTaskUpdatePayload {
  const message = values.message.trim()
  return {
    recipient_ids: values.recipient_ids,
    ...(message !== '' ? { message } : {}),
  }
}

function toggleRecipient(current: number[], id: number, checked: boolean): number[] {
  if (checked) {
    return current.includes(id) ? current : [...current, id]
  }
  return current.filter((existing) => existing !== id)
}

interface RecipientCheckboxProps {
  candidate: TaskNamedRef
  roleLabel: string
  selected: number[]
  onToggle: (id: number, checked: boolean) => void
}

/** One selectable row of the picker (module-level: never defined inside the dialog, frontend.md §10). */
function RecipientCheckbox({ candidate, roleLabel, selected, onToggle }: RecipientCheckboxProps) {
  const checked = selected.includes(candidate.id)
  return (
    <label className="flex items-center gap-2 py-1 text-sm">
      <Checkbox checked={checked} onCheckedChange={(next) => onToggle(candidate.id, next === true)} />
      <span className="truncate">{candidate.name}</span>
      <span className="text-xs text-muted-foreground">{roleLabel}</span>
    </label>
  )
}

interface TaskRequestUpdateDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  task: TaskDetailWithPermissions
}

/**
 * "Richiedi aggiornamento" (spec 0118 D-10..D-14): the recipient picker is
 * built from THIS task's own `assignees`/`watchers`, already in the loaded
 * detail — no user-search endpoint exists for it, deliberately (D-11).
 * Creator/requester are never candidates unless they also happen to be an
 * assignee or a watcher.
 */
export function TaskRequestUpdateDialog({ open, onOpenChange, task }: TaskRequestUpdateDialogProps) {
  const { t } = useTranslation()
  const schema = buildRequestUpdateSchema(t)
  const hasCandidates = task.assignees.length > 0 || task.watchers.length > 0

  const form = useForm<RequestUpdateFormValues>({
    resolver: zodResolver(schema),
    defaultValues: requestUpdateDefaultValues(),
  })

  const requestUpdateMutation = useRequestTaskUpdate({
    taskId: task.id,
    onSuccess: () => {
      toast.success(t('tasks.actions.requestUpdate.success'))
      onOpenChange(false)
      form.reset(requestUpdateDefaultValues())
    },
  })

  const onSubmit = async (values: RequestUpdateFormValues) => {
    try {
      await requestUpdateMutation.mutateAsync(buildRequestUpdatePayload(values))
    } catch (error) {
      // A field-scoped 422 (unknown recipient AC-050, message too long
      // AC-054) is wired inline; any other failure (409 bloccato, 422 fase
      // sbagliata) falls back to the shared toast split (AC-064).
      if (axios.isAxiosError(error) && error.response?.status === 422) {
        const errors = error.response.data?.errors
        if (errors?.recipient_ids || errors?.message) {
          applyServerValidationErrors(error, form.setError, ['recipient_ids', 'message'])
          return
        }
      }
      toast.error(actionErrorMessage(t, error))
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          form.reset(requestUpdateDefaultValues())
        }
        onOpenChange(next)
      }}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('tasks.actions.requestUpdate.title')}</DialogTitle>
          <DialogDescription>{t('tasks.actions.requestUpdate.description')}</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            id="task-request-update-form"
            className="flex flex-col gap-4"
            onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
          >
            <FormField
              control={form.control}
              name="recipient_ids"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('tasks.actions.requestUpdate.recipients')}</FormLabel>
                  {hasCandidates ? (
                    <FormControl>
                      <div className="flex max-h-48 flex-col gap-1 overflow-auto rounded-md border border-field-border p-2">
                        {task.assignees.map((candidate) => (
                          <RecipientCheckbox
                            key={`assignee-${candidate.id}`}
                            candidate={candidate}
                            roleLabel={t('tasks.form.assignees')}
                            selected={field.value}
                            onToggle={(id, checked) => field.onChange(toggleRecipient(field.value, id, checked))}
                          />
                        ))}
                        {task.watchers.map((candidate) => (
                          <RecipientCheckbox
                            key={`watcher-${candidate.id}`}
                            candidate={candidate}
                            roleLabel={t('tasks.form.watchers')}
                            selected={field.value}
                            onToggle={(id, checked) => field.onChange(toggleRecipient(field.value, id, checked))}
                          />
                        ))}
                      </div>
                    </FormControl>
                  ) : (
                    <p className="text-sm text-muted-foreground">
                      {t('tasks.actions.requestUpdate.recipientsEmpty')}
                    </p>
                  )}
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="message"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('tasks.actions.requestUpdate.message')}</FormLabel>
                  <FormControl>
                    <Textarea
                      rows={3}
                      maxLength={MESSAGE_MAX_LENGTH}
                      placeholder={t('tasks.actions.requestUpdate.messagePlaceholder')}
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
          <Button type="button" variant="outline" className="bg-card" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="submit"
            form="task-request-update-form"
            disabled={requestUpdateMutation.isPending || !hasCandidates}
          >
            {t('tasks.actions.requestUpdate.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
