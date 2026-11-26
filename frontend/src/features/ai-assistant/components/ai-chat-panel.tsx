import { useTranslation } from 'react-i18next'
import { AiComposer } from '@/features/ai-assistant/components/ai-composer'
import { AiEmptyState } from '@/features/ai-assistant/components/ai-empty-state'
import { AiMessageList } from '@/features/ai-assistant/components/ai-message-list'
import { AiNewConversationProposal } from '@/features/ai-assistant/components/ai-new-conversation-proposal'
import { AiNotConfigured } from '@/features/ai-assistant/components/ai-not-configured'
import { useAiAssistantConfig } from '@/features/ai-assistant/hooks/use-ai-assistant-config'
import { useAiComposer } from '@/features/ai-assistant/hooks/use-ai-composer'
import { useAiActiveTurn } from '@/features/ai-assistant/hooks/use-ai-active-turn'
import { useAiConversation } from '@/features/ai-assistant/hooks/use-ai-conversation'
import { isAiTurnAwaitingConfirmation, isAiTurnFinal } from '@/features/ai-assistant/turn-status'
import type { AiAssistantConfig, AiConversation, AiMessage, AiTurn } from '@/features/ai-assistant/types'
import { cn } from '@/lib/utils'

const NO_MESSAGES: AiMessage[] = []

/**
 * Hides a closed turn once its reply is among the saved messages (no duplicate bubble); a turn
 * with a preview stays, reduced to the closed card with its outcome.
 */
function turnToShow(turn: AiTurn | null, messages: AiMessage[]): AiTurn | null {
  const replyId = turn?.assistant_message?.id
  if (turn === null || replyId === undefined || !messages.some((message) => message.id === replyId)) {
    return turn
  }
  return turn.preview === null ? null : { ...turn, assistant_text: '', steps: [] }
}

export type AiChatVariant = 'widget' | 'page'

export interface AiChatPanelProps {
  variant: AiChatVariant
  /** Selected conversation (controlled); `null` is a new, not yet created one. */
  conversationId: number | null
  /** Fired when the panel creates a conversation, so the owner can select it. */
  onConversationChange: (id: number | null) => void
  /** Sends the `general` guide instead of the one of the current page (AC-024). */
  forceGeneralGuide?: boolean
  className?: string
}

/** Chat surface shared by the floating widget and the /ai-assistant page. */
export function AiChatPanel({ className, ...props }: AiChatPanelProps) {
  const { t } = useTranslation()
  const configQuery = useAiAssistantConfig()

  let content
  if (configQuery.isLoading) {
    content = (
      <p role="status" className="p-4 text-xs text-muted-foreground">
        {t('aiAssistant.loading')}
      </p>
    )
  } else if (!configQuery.data) {
    content = (
      <p role="alert" className="p-4 text-xs text-destructive">
        {t('aiAssistant.loadFailed')}
      </p>
    )
  } else if (!configQuery.data.configured) {
    content = <AiNotConfigured />
  } else {
    content = <AiChatBody config={configQuery.data} {...props} />
  }

  // The widget lives in the chat dock's card (spec 0432): it sits on the card and its
  // bubbles and composer move to the tints above it (`group-data-[variant=widget]/ai-chat:`).
  return (
    <div
      data-variant={props.variant}
      className={cn(
        'group/ai-chat flex min-h-0 flex-1 flex-col',
        props.variant === 'widget' ? 'bg-card' : 'bg-background',
        className,
      )}
    >
      {content}
    </div>
  )
}

function AiChatBody({
  config,
  variant,
  conversationId,
  onConversationChange,
  forceGeneralGuide,
}: AiChatPanelProps & { config: AiAssistantConfig }) {
  const { t } = useTranslation()
  const conversationQuery = useAiConversation(conversationId)
  const activeTurn = useAiActiveTurn({
    conversationId,
    serverTurn: conversationQuery.data?.active_turn,
    pollIntervalMs: config.limits.poll_interval_ms,
  })
  const composer = useAiComposer({
    config,
    conversationId,
    onConversationCreated: (conversation: AiConversation) => onConversationChange(conversation.id),
    onTurnStarted: activeTurn.track,
    turnActive: activeTurn.turn !== null && !isAiTurnFinal(activeTurn.turn.status),
    awaitingPreview: activeTurn.turn !== null && isAiTurnAwaitingConfirmation(activeTurn.turn.status),
    forceGeneralGuide,
  })
  const messages = conversationQuery.data?.messages ?? NO_MESSAGES
  const shownTurn = turnToShow(activeTurn.turn, messages)
  const isLoadingMessages = conversationId !== null && conversationQuery.isLoading
  const isEmpty = messages.length === 0 && shownTurn === null && !isLoadingMessages

  return (
    <div className={cn('mx-auto flex min-h-0 w-full flex-1 flex-col', variant === 'page' && 'max-w-3xl')}>
      {isLoadingMessages ? (
        <p role="status" className="p-4 text-xs text-muted-foreground">
          {t('aiAssistant.loading')}
        </p>
      ) : isEmpty ? (
        <div className="flex min-h-0 flex-1 overflow-y-auto">
          <AiEmptyState onPickSuggestion={composer.setDraft} />
        </div>
      ) : (
        <AiMessageList messages={messages} turn={shownTurn} />
      )}
      {conversationQuery.data?.propose_new_conversation === true && (
        <AiNewConversationProposal onStart={() => onConversationChange(null)} />
      )}
      <AiComposer composer={composer} allowedModules={config.mention_modules} />
    </div>
  )
}
