import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { ExternalLink, History, MessageSquarePlus, MessagesSquare } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { AiChatPanel } from '@/features/ai-assistant/components/ai-chat-panel'
import { AiConversationList } from '@/features/ai-assistant/components/ai-conversation-list'
import { AiDockAvatar } from '@/features/ai-assistant/components/ai-dock-avatar'
import { useAiAssistantConfig } from '@/features/ai-assistant/hooks/use-ai-assistant-config'
import { AI_ASSISTANT_ROUTE } from '@/features/ai-assistant/routes'
import { useActiveWorkspaceSlug } from '@/features/workspaces/use-active-workspace-slug'
import { workspacePath } from '@/features/workspaces/workspace-path'
import { CHAT_DOCK_HEADER_BUTTON_CLASS } from '@/features/work-order-chat/dock/dock-styles'
import { DockWindowFrame } from '@/features/work-order-chat/dock/dock-window-frame'

interface AiDockWindowProps {
  minimized: boolean
  onToggleMinimized: () => void
  onClose: () => void
}

/**
 * The assistant as a conversation of the chat dock (spec 0432), in place of
 * the floating launcher: new conversation, history and the full page in the
 * header, the shared widget chat below. Loaded lazily on first open (keeps the
 * chat out of the app shell bundle) and kept mounted while minimized, so an
 * ongoing answer and the draft survive; closing the window drops them.
 */
export default function AiDockWindow({ minimized, onToggleMinimized, onClose }: AiDockWindowProps) {
  const { t } = useTranslation()
  const slug = useActiveWorkspaceSlug()
  const pageEnabled = useAiAssistantConfig().data?.page_enabled ?? false
  const [conversationId, setConversationId] = useState<number | null>(null)
  const [showHistory, setShowHistory] = useState(false)
  const fullPagePath = slug ? workspacePath(slug, AI_ASSISTANT_ROUTE) : AI_ASSISTANT_ROUTE

  const select = (id: number | null) => {
    setConversationId(id)
    setShowHistory(false)
  }

  return (
    <DockWindowFrame
      title={t('aiAssistant.title')}
      leading={<AiDockAvatar className="size-4 bg-primary-foreground text-primary" />}
      minimized={minimized}
      onToggleMinimized={onToggleMinimized}
      onClose={onClose}
      openWidthClass="sm:w-96"
      keepMounted
      actions={
        <>
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            className={CHAT_DOCK_HEADER_BUTTON_CLASS}
            aria-label={t('aiAssistant.newConversation')}
            onClick={() => select(null)}
          >
            <MessageSquarePlus aria-hidden="true" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            className={CHAT_DOCK_HEADER_BUTTON_CLASS}
            aria-pressed={showHistory}
            aria-label={showHistory ? t('aiAssistant.backToChat') : t('aiAssistant.history')}
            onClick={() => setShowHistory((current) => !current)}
          >
            {showHistory ? <MessagesSquare aria-hidden="true" /> : <History aria-hidden="true" />}
          </Button>
          {pageEnabled ? (
            <Button asChild variant="ghost" size="icon-xs" className={CHAT_DOCK_HEADER_BUTTON_CLASS}>
              <Link to={fullPagePath} state={{ conversationId }} aria-label={t('aiAssistant.openFullPage')}>
                <ExternalLink aria-hidden="true" />
              </Link>
            </Button>
          ) : null}
        </>
      }
    >
      {showHistory ? (
        <div className="flex min-h-0 flex-1 flex-col p-2">
          <AiConversationList selectedId={conversationId} onSelect={select} />
        </div>
      ) : (
        <AiChatPanel variant="widget" conversationId={conversationId} onConversationChange={setConversationId} />
      )}
    </DockWindowFrame>
  )
}
