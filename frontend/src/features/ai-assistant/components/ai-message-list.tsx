import { useTranslation } from 'react-i18next'
import { AiMessageBubble } from '@/features/ai-assistant/components/ai-message-bubble'
import { AiTurnStatus } from '@/features/ai-assistant/components/ai-turn-status'
import { useStickToBottom } from '@/features/ai-assistant/hooks/use-stick-to-bottom'
import type { AiMessage, AiTurn } from '@/features/ai-assistant/types'

interface Props {
  messages: AiMessage[]
  /** The turn being followed, `null` when there is nothing to show beyond the saved messages. */
  turn: AiTurn | null
}

/** Message log with autoscroll while the turn grows (steps, partial text, outcome). */
export function AiMessageList({ messages, turn }: Props) {
  const { t } = useTranslation()
  const turnVersion = turn ? `${turn.status}:${turn.steps.length}:${turn.assistant_text.length}` : 'none'
  const { containerRef, onScroll } = useStickToBottom(`${messages.length}:${turnVersion}`)

  return (
    <div ref={containerRef} onScroll={onScroll} className="min-h-0 flex-1 overflow-y-auto overflow-x-hidden p-3">
      <ul
        role="log"
        aria-label={t('aiAssistant.messages.label')}
        aria-busy={turn !== null && (turn.status === 'queued' || turn.status === 'running')}
        className="flex flex-col gap-3"
      >
        {messages.map((message) => (
          <AiMessageBubble key={message.id} message={message} />
        ))}
        {turn ? <AiTurnStatus turn={turn} /> : null}
      </ul>
    </div>
  )
}
