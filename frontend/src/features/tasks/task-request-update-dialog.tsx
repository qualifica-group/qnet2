import { useId } from 'react'
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
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { cn } from '@/lib/utils'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { actionErrorMessage } from '@/features/tasks/task-action-error-message'
import { useRequestTaskUpdate } from '@/features/tasks/use-task-mutations'
import type {
  RequestTaskUpdatePayload,
  TaskDetailWithPermissions,
  TaskNamedRef,
  TaskRequestUpdateTarget,
} from '@/features/tasks/types'

/** Backend `message` column bounds (spec 0153 D-14, data_contract: `min:3|max:2000`). */
const MESSAGE_MIN_LENGTH = 3
const MESSAGE_MAX_LENGTH = 2000

function buildRequestUpdateSchema(t: TFunction) {
  return z.object({
    target: z.enum(['assignees', 'observers', 'all']),
    message: z
      .string()
      .trim()
      .min(MESSAGE_MIN_LENGTH, t('tasks.actions.requestUpdate.messageTooShort'))
      .max(MESSAGE_MAX_LENGTH, t('tasks.actions.requestUpdate.messageTooLong')),
  })
}

type RequestUpdateFormValues = z.infer<ReturnType<typeof buildRequestUpdateSchema>>

const REQUEST_UPDATE_DEFAULT_VALUES: RequestUpdateFormValues = { target: 'assignees', message: '' }

function buildRequestUpdatePayload(values: RequestUpdateFormValues): RequestTaskUpdatePayload {
  return { target: values.target, message: values.message.trim() }
}

/** Count of distinct recipients across both lists (D-14 `all`), a user in both counted once. */
function uniqueRecipientCount(assignees: TaskNamedRef[], watchers: TaskNamedRef[]): number {
  const ids = new Set([...assignees, ...watchers].map((ref) => ref.id))
  return ids.size
}

interface RequestUpdateTargetOption {
  value: TaskRequestUpdateTarget
  label: string
  count: number
  /** Extra copy shown under the option (D-14: watchers are CC'd under `assignees`). */
  hint?: string
}

function buildTargetOptions(task: TaskDetailWithPermissions, t: TFunction): RequestUpdateTargetOption[] {
  return [
    {
      value: 'assignees',
      label: t('tasks.actions.requestUpdate.targetAssignees'),
      count: task.assignees.length,
      hint: task.watchers.length > 0 ? t('tasks.actions.requestUpdate.targetAssigneesHint') : undefined,
    },
    {
      value: 'observers',
      label: t('tasks.actions.requestUpdate.targetObservers'),
      count: task.watchers.length,
    },
    {
      value: 'all',
      label: t('tasks.actions.requestUpdate.targetAll'),
      count: uniqueRecipientCount(task.assignees, task.watchers),
    },
  ]
}

interface TargetOptionRowProps {
  option: RequestUpdateTargetOption
  name: string
  checked: boolean
  onSelect: () => void
}

/**
 * One radio row of the target picker (module-level: never defined inside the
 * dialog, frontend.md §10). Native `<input type="radio">`: no `radio-group`
 * component exists in `components/ui/` (same documented tradeoff as the
 * advanced-filters `RadioAdvancedFilterField`).
 */
function TargetOptionRow({ option, name, checked, onSelect }: TargetOptionRowProps) {
  const disabled = option.count === 0
  return (
    <label
      className={cn(
        'flex flex-col gap-0.5 rounded-md border border-field-border p-2 text-sm',
        disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
      )}
    >
      <span className="flex items-center gap-2">
        <input
          type="radio"
          name={name}
          checked={checked}
          disabled={disabled}
          onChange={onSelect}
          // Explicit `aria-label` (rather than relying on the wrapping label's
          // full text): "assignees" and "all" otherwise share the substring
          // "Assignees", making them indistinguishable by accessible name.
          aria-label={`${option.label} (${option.count})`}
          className="size-3.5"
        />
        <span>{option.label}</span>
        <span className="text-xs text-muted-foreground">({option.count})</span>
      </span>
      {option.hint ? <span className="pl-6 text-xs text-muted-foreground">{option.hint}</span> : null}
    </label>
  )
}

interface TaskRequestUpdateDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  task: TaskDetailWithPermissions
}

/**
 * "Richiedi aggiornamento" (spec 0153 D-14, RECTIFIES spec 0118's free
 * recipient picker): the destination is one of three FIXED groups computed
 * from this task's own `assignees`/`watchers`, never an arbitrary id list —
 * `assignees` also CCs every watcher server-side (`is_cc: true` on their
 * notification), `observers` reaches only the watchers, `all` reaches both
 * outright. A group with zero recipients is disabled. The message is always
 * mandatory (3..2000 chars).
 */
export function TaskRequestUpdateDialog({ open, onOpenChange, task }: TaskRequestUpdateDialogProps) {
  const { t } = useTranslation()
  const groupName = useId()
  const schema = buildRequestUpdateSchema(t)
  const options = buildTargetOptions(task, t)
  const hasRecipients = options.some((option) => option.count > 0)

  const form = useForm<RequestUpdateFormValues>({
    resolver: zodResolver(schema),
    defaultValues: REQUEST_UPDATE_DEFAULT_VALUES,
  })

  const requestUpdateMutation = useRequestTaskUpdate({
    taskId: task.id,
    onSuccess: () => {
      toast.success(t('tasks.actions.requestUpdate.success'))
      onOpenChange(false)
      form.reset(REQUEST_UPDATE_DEFAULT_VALUES)
    },
  })

  const onSubmit = async (values: RequestUpdateFormValues) => {
    try {
      await requestUpdateMutation.mutateAsync(buildRequestUpdatePayload(values))
    } catch (error) {
      // A field-scoped 422 (target without recipients, message out of bounds)
      // is wired inline; any other failure (403 observer/no ability, 422
      // wrong phase, 409 bloccato) falls back to the shared toast split.
      if (axios.isAxiosError(error) && error.response?.status === 422) {
        const errors = error.response.data?.errors
        if (errors?.target || errors?.message) {
          applyServerValidationErrors(error, form.setError, ['target', 'message'])
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
          form.reset(REQUEST_UPDATE_DEFAULT_VALUES)
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
              name="target"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('tasks.actions.requestUpdate.target')}</FormLabel>
                  {hasRecipients ? (
                    <FormControl>
                      <div role="radiogroup" className="flex flex-col gap-2">
                        {options.map((option) => (
                          <TargetOptionRow
                            key={option.value}
                            option={option}
                            name={groupName}
                            checked={field.value === option.value}
                            onSelect={() => field.onChange(option.value)}
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
                  <FormLabel required>{t('tasks.actions.requestUpdate.message')}</FormLabel>
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
            disabled={requestUpdateMutation.isPending || !hasRecipients}
          >
            {t('tasks.actions.requestUpdate.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
