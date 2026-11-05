import { useId } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { createChatMessageSchema, type ChatMessageFormValues } from '@/features/work-order-chat/chat-schema'

interface WorkOrderChatComposerProps {
  initialBody?: string
  isPending: boolean
  /** Resolves when the message was saved; the form then resets. */
  onSubmit: (body: string) => Promise<unknown>
  onCancel?: () => void
  submitLabel: string
}

/** Message form (new message or inline edit): RHF + Zod, send disabled until valid. */
export function WorkOrderChatComposer({
  initialBody = '',
  isPending,
  onSubmit,
  onCancel,
  submitLabel,
}: WorkOrderChatComposerProps) {
  const { t } = useTranslation()
  const errorId = useId()
  const form = useForm<ChatMessageFormValues>({
    resolver: zodResolver(createChatMessageSchema(t)),
    mode: 'onChange',
    defaultValues: { body: initialBody },
  })
  const { errors, isValid } = form.formState
  // mode onChange: an error exists only once the user has typed.
  const error = errors.body?.message

  const submit = form.handleSubmit(async ({ body }) => {
    try {
      await onSubmit(body)
      form.reset({ body: '' })
    } catch {
      // The mutation already surfaced the failure; keep the text so it can be resent.
    }
  })

  return (
    <form onSubmit={submit} noValidate className="flex flex-col gap-1.5">
      <Textarea
        {...form.register('body')}
        aria-label={t('workOrders.chat.composerLabel')}
        placeholder={t('workOrders.chat.composerPlaceholder')}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
        rows={2}
        className="min-h-10 py-1.5 text-sm"
      />
      {error ? (
        <span id={errorId} role="alert" className="text-xs text-destructive">
          {error}
        </span>
      ) : null}
      <div className="flex justify-end gap-1.5">
        {onCancel ? (
          <Button type="button" variant="outline" size="xs" onClick={onCancel} disabled={isPending}>
            {t('workOrders.chat.cancel')}
          </Button>
        ) : null}
        <Button type="submit" size="xs" disabled={!isValid || isPending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  )
}
