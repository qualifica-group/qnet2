import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import axios from 'axios'
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

/** Backend `message` bounds, identical for tasks (spec 0153 D-14) and work orders (spec 0352): `min:3|max:2000`. */
const MESSAGE_MIN_LENGTH = 3
const MESSAGE_MAX_LENGTH = 2000

export interface RequestUpdateOption<T extends string> {
  value: T
  label: string
  count: number
  /** Extra copy shown under the option (the copy recipients of the group). */
  hint?: string
}

export interface RequestUpdateTexts {
  title: string
  description: string
  target: string
  recipientsEmpty: string
  message: string
  messagePlaceholder: string
  messageTooShort: string
  messageTooLong: string
  submit: string
  success: string
}

export interface RequestUpdatePayload<T extends string> {
  target: T
  message: string
}

/** `target` stays a plain string in the form: the schema restricts it to the option values, the submit narrows it back to `T`. */
interface RequestUpdateFormValues {
  target: string
  message: string
}

function buildSchema<T extends string>(options: RequestUpdateOption<T>[], texts: RequestUpdateTexts) {
  return z.object({
    target: z.string().refine((value) => options.some((option) => option.value === value)),
    message: z.string().trim().min(MESSAGE_MIN_LENGTH, texts.messageTooShort).max(MESSAGE_MAX_LENGTH, texts.messageTooLong),
  })
}

interface TargetOptionRowProps<T extends string> {
  option: RequestUpdateOption<T>
  name: string
  checked: boolean
  onSelect: () => void
}

/**
 * One radio row of the target picker (module-level: never defined inside the
 * dialog, frontend.md §10). Native `<input type="radio">`: no `radio-group`
 * component exists in `components/ui/`.
 */
function TargetOptionRow<T extends string>({ option, name, checked, onSelect }: TargetOptionRowProps<T>) {
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
          // Explicit `aria-label`: a group label is often a substring of "all"
          // (e.g. "Assignees" / "Assignees and watchers"), so the wrapping
          // label text alone would not tell them apart.
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

interface RequestUpdateDialogProps<T extends string> {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Unique `id` of the form, so the footer submit button can target it. */
  formId: string
  options: RequestUpdateOption<T>[]
  defaultTarget: T
  texts: RequestUpdateTexts
  isPending: boolean
  /** Sends the request; a rejection is routed to inline field errors (422) or `onError`. */
  onSubmit: (payload: RequestUpdatePayload<T>) => Promise<unknown>
  /** Any failure that is not a field-scoped 422 (the caller owns the toast copy). */
  onError: (error: unknown) => void
}

/**
 * "Richiedi aggiornamento" dialog shared by tasks (spec 0153 D-14) and work
 * orders (spec 0352 D-5): the destination is one of a few FIXED recipient
 * groups (radio rows with their counts, a zero-count group disabled) and the
 * message is always mandatory (3..2000 chars). The caller owns the groups, the
 * copy and the request; this component owns the form and the error routing.
 */
export function RequestUpdateDialog<T extends string>({
  open,
  onOpenChange,
  formId,
  options,
  defaultTarget,
  texts,
  isPending,
  onSubmit,
  onError,
}: RequestUpdateDialogProps<T>) {
  const { t } = useTranslation()
  const groupName = useId()
  const hasRecipients = options.some((option) => option.count > 0)
  const defaultValues: RequestUpdateFormValues = { target: defaultTarget, message: '' }

  const form = useForm<RequestUpdateFormValues>({
    resolver: zodResolver(buildSchema(options, texts)),
    defaultValues,
  })

  const submit = async (values: RequestUpdateFormValues) => {
    const selected = options.find((option) => option.value === values.target)
    if (!selected) {
      return
    }
    try {
      await onSubmit({ target: selected.value, message: values.message.trim() })
      toast.success(texts.success)
      onOpenChange(false)
      form.reset(defaultValues)
    } catch (error) {
      // A field-scoped 422 (group without recipients, message out of bounds)
      // is wired inline; anything else falls back to the caller's toast.
      if (axios.isAxiosError(error) && error.response?.status === 422) {
        const errors = error.response.data?.errors
        if (errors?.target || errors?.message) {
          applyServerValidationErrors(error, form.setError, ['target', 'message'])
          return
        }
      }
      onError(error)
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          form.reset(defaultValues)
        }
        onOpenChange(next)
      }}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{texts.title}</DialogTitle>
          <DialogDescription>{texts.description}</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            id={formId}
            className="flex flex-col gap-4"
            onSubmit={(event) => void form.handleSubmit(submit)(event)}
          >
            <FormField
              control={form.control}
              name="target"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{texts.target}</FormLabel>
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
                    <p className="text-sm text-muted-foreground">{texts.recipientsEmpty}</p>
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
                  <FormLabel required>{texts.message}</FormLabel>
                  <FormControl>
                    <Textarea
                      rows={3}
                      maxLength={MESSAGE_MAX_LENGTH}
                      placeholder={texts.messagePlaceholder}
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
          <Button type="submit" form={formId} disabled={isPending || !hasRecipients}>
            {texts.submit}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
