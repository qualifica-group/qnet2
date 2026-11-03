import { memo } from 'react'
import { useTranslation } from 'react-i18next'
import { Bot, User } from 'lucide-react'
import { cn } from '@/lib/utils'
import { AiAttachmentChip } from '@/features/ai-assistant/components/ai-attachment-chip'
import { AiMarkdown } from '@/features/ai-assistant/components/ai-markdown'
import { getAiMentionModule } from '@/features/ai-assistant/mention-modules'
import type { AiMessage } from '@/features/ai-assistant/types'

interface Props {
  message: AiMessage
}

/** One saved message: user text is plain, assistant text goes through the safe markdown renderer. */
export const AiMessageBubble = memo(function AiMessageBubble({ message }: Props) {
  const { t } = useTranslation()
  const isUser = message.role === 'user'

  return (
    <li className={cn('flex gap-2', isUser && 'flex-row-reverse')}>
      <BubbleAvatar isUser={isUser} />
      <div className={cn('flex min-w-0 max-w-[85%] flex-col gap-1', isUser && 'items-end')}>
        <span className="sr-only">{t(isUser ? 'aiAssistant.messages.you' : 'aiAssistant.messages.assistant')}</span>
        <div
          className={cn(
            'rounded-lg px-3 py-2 text-sm',
            isUser ? 'bg-primary text-primary-foreground' : 'border border-border bg-card text-card-foreground',
          )}
        >
          {isUser ? <p className="whitespace-pre-wrap break-words [overflow-wrap:anywhere]">{message.content}</p> : <AiMarkdown content={message.content} />}
        </div>
        {message.mentions.length > 0 || message.attachments.length > 0 ? (
          <div className="flex flex-wrap gap-1">
            {message.mentions.map((mention) => {
              const definition = getAiMentionModule(mention.module)
              return (
                <span
                  key={`${mention.module}-${mention.id}`}
                  className="inline-flex max-w-full items-center gap-1 rounded-md border border-border bg-card px-2 py-0.5 text-xs text-card-foreground"
                >
                  <definition.icon className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                  <span className="truncate">
                    {t('aiAssistant.messages.mentionChip', { module: t(definition.labelKey), label: mention.label })}
                  </span>
                </span>
              )
            })}
            {message.attachments.map((attachment) => (
              <AiAttachmentChip key={attachment.id} messageId={message.id} attachment={attachment} />
            ))}
          </div>
        ) : null}
      </div>
    </li>
  )
})

export function BubbleAvatar({ isUser }: { isUser: boolean }) {
  const Icon = isUser ? User : Bot
  return (
    <span
      aria-hidden="true"
      className="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground"
    >
      <Icon className="size-3.5" />
    </span>
  )
}
