import { useTranslation } from 'react-i18next'
import { AiComposer } from '@/features/ai-assistant/components/ai-composer'
import { AiEmptyState } from '@/features/ai-assistant/components/ai-empty-state'
import { AiMessageList } from '@/features/ai-assistant/components/ai-message-list'
import { AiNotConfigured } from '@/features/ai-assistant/components/ai-not-configured'
import { useAiAssistantConfig } from '@/features/ai-assistant/hooks/use-ai-assistant-config'
import { useAiComposer } from '@/features/ai-assistant/hooks/use-ai-composer'
import { useAiConversation } from '@/features/ai-assistant/hooks/use-ai-conversation'
import type { AiAssistantConfig, AiConversation, AiMessage } from '@/features/ai-assistant/types'
import { cn } from '@/lib/utils'

const NO_MESSAGES: AiMessage[] = []

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

  return <div className={cn('flex min-h-0 flex-1 flex-col bg-background', className)}>{content}</div>
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
  const composer = useAiComposer({
    config,
    conversationId,
    onConversationCreated: (conversation: AiConversation) => onConversationChange(conversation.id),
    forceGeneralGuide,
  })
  const messages = conversationQuery.data?.messages ?? NO_MESSAGES
  const isLoadingMessages = conversationId !== null && conversationQuery.isLoading
  const isEmpty = messages.length === 0 && !composer.isSending && !isLoadingMessages

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
        <AiMessageList messages={messages} isSending={composer.isSending} streamingText={composer.streamingText} />
      )}
      <AiComposer composer={composer} allowedModules={config.mention_modules} />
    </div>
  )
}
