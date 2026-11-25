import { useId, type KeyboardEvent } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { SendHorizontal } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { createChatMessageSchema, type ChatMessageFormValues } from '@/features/work-order-chat/chat-schema'
import { CHAT_DOCK_SOFT_BUTTON_CLASS } from '@/features/work-order-chat/dock/dock-styles'

interface WorkOrderChatComposerProps {
  initialBody?: string
  isPending: boolean
  /** Resolves when the message was saved; the form then resets. */
  onSubmit: (body: string) => Promise<unknown>
  onCancel?: () => void
  submitLabel: string
}

/**
 * Message form (new message or inline edit): RHF + Zod, send disabled until
 * valid. Enter sends, Shift+Enter starts a new line, like the assistant's
 * composer; a new message has the assistant's icon send button.
 */
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

  const canSubmit = isValid && !isPending

  const handleKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
    if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
      event.preventDefault()
      if (canSubmit) {
        void submit()
      }
    }
  }

  return (
    <form onSubmit={submit} noValidate className="flex flex-col gap-1.5">
      <div className="flex items-end gap-1.5">
        <Textarea
          {...form.register('body')}
          aria-label={t('workOrders.chat.composerLabel')}
          placeholder={t('workOrders.chat.composerPlaceholder')}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? errorId : undefined}
          rows={1}
          onKeyDown={handleKeyDown}
          className="max-h-32 min-h-9 resize-none py-1.5 text-sm"
        />
        {onCancel ? null : (
          <Button type="submit" size="icon-sm" aria-label={submitLabel} disabled={!canSubmit}>
            <SendHorizontal className="size-3.5" aria-hidden="true" />
          </Button>
        )}
      </div>
      {error ? (
        <span id={errorId} role="alert" className="text-xs text-destructive">
          {error}
        </span>
      ) : null}
      {onCancel ? (
        <div className="flex justify-end gap-1.5">
          <Button
            type="button"
            variant="outline"
            size="xs"
            className={CHAT_DOCK_SOFT_BUTTON_CLASS}
            onClick={onCancel}
            disabled={isPending}
          >
            {t('workOrders.chat.cancel')}
          </Button>
          <Button type="submit" size="xs" disabled={!canSubmit}>
            {submitLabel}
          </Button>
        </div>
      ) : null}
    </form>
  )
}
