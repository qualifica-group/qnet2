import { useTranslation } from 'react-i18next'
import { AiMarkdown } from '@/features/ai-assistant/components/ai-markdown'
import { AiMessageBubble, BubbleAvatar } from '@/features/ai-assistant/components/ai-message-bubble'
import { useStickToBottom } from '@/features/ai-assistant/hooks/use-stick-to-bottom'
import type { AiMessage } from '@/features/ai-assistant/types'

interface Props {
  messages: AiMessage[]
  isSending: boolean
  /** Assistant reply accumulated so far by the stream. */
  streamingText: string
}

/** Message log with autoscroll while streaming and a "typing" indicator before the first delta. */
export function AiMessageList({ messages, isSending, streamingText }: Props) {
  const { t } = useTranslation()
  const last = messages.at(-1)
  // `message.completed` lands in the cache a moment before the stream settles: avoid showing the reply twice.
  const showStreaming = isSending && !(last?.role === 'assistant' && last.content === streamingText)
  const { containerRef, onScroll } = useStickToBottom(`${messages.length}:${streamingText.length}:${isSending}`)

  return (
    <div ref={containerRef} onScroll={onScroll} className="min-h-0 flex-1 overflow-y-auto overflow-x-hidden p-3">
      <ul
        role="log"
        aria-label={t('aiAssistant.messages.label')}
        aria-busy={isSending}
        className="flex flex-col gap-3"
      >
        {messages.map((message) => (
          <AiMessageBubble key={message.id} message={message} />
        ))}
        {showStreaming ? (
          <li className="flex gap-2">
            <BubbleAvatar isUser={false} />
            <div className="min-w-0 max-w-[85%] rounded-lg border border-border bg-card px-3 py-2 text-sm text-card-foreground">
              {streamingText ? (
                <AiMarkdown content={streamingText} />
              ) : (
                <p role="status" className="text-xs text-muted-foreground">
                  {t('aiAssistant.messages.typing')}
                </p>
              )}
            </div>
          </li>
        ) : null}
      </ul>
    </div>
  )
}
