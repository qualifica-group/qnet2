import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { ExternalLink, History, MessageSquarePlus, MessagesSquare, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { AiChatPanel } from '@/features/ai-assistant/components/ai-chat-panel'
import { AiConversationList } from '@/features/ai-assistant/components/ai-conversation-list'
import { AI_ASSISTANT_ROUTE } from '@/features/ai-assistant/routes'
import { useActiveWorkspaceSlug } from '@/features/workspaces/use-active-workspace-slug'
import { workspacePath } from '@/features/workspaces/workspace-path'
import { cn } from '@/lib/utils'

interface Props {
  open: boolean
  onClose: () => void
}

/**
 * Body of the floating widget, loaded lazily on first open (keeps the chat
 * out of the app shell bundle). Stays mounted while closed so an ongoing
 * stream and the draft survive closing the window.
 */
export default function FloatingAiPanel({ open, onClose }: Props) {
  const { t } = useTranslation()
  const slug = useActiveWorkspaceSlug()
  const [conversationId, setConversationId] = useState<number | null>(null)
  const [showHistory, setShowHistory] = useState(false)
  const fullPagePath = slug ? workspacePath(slug, AI_ASSISTANT_ROUTE) : AI_ASSISTANT_ROUTE

  const select = (id: number | null) => {
    setConversationId(id)
    setShowHistory(false)
  }

  return (
    <aside
      aria-label={t('aiAssistant.title')}
      className={cn(
        'fixed inset-0 z-40 flex-col overflow-hidden border-border bg-background shadow-lg',
        'sm:inset-auto sm:right-4 sm:bottom-16 sm:h-[min(36rem,calc(100dvh-5rem))] sm:w-96 sm:rounded-lg sm:border',
        open ? 'flex' : 'hidden',
      )}
    >
      <header className="flex items-center gap-1 border-b border-border bg-surface px-2 py-1.5">
        <h2 className="min-w-0 flex-1 truncate px-1 text-sm font-semibold">{t('aiAssistant.title')}</h2>
        <Button type="button" variant="ghost" size="icon-sm" aria-label={t('aiAssistant.newConversation')} onClick={() => select(null)}>
          <MessageSquarePlus className="size-3.5" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          aria-pressed={showHistory}
          aria-label={showHistory ? t('aiAssistant.backToChat') : t('aiAssistant.history')}
          onClick={() => setShowHistory((current) => !current)}
        >
          {showHistory ? <MessagesSquare className="size-3.5" /> : <History className="size-3.5" />}
        </Button>
        <Button asChild variant="ghost" size="icon-sm">
          <Link to={fullPagePath} state={{ conversationId }} aria-label={t('aiAssistant.openFullPage')}>
            <ExternalLink className="size-3.5" />
          </Link>
        </Button>
        <Button type="button" variant="ghost" size="icon-sm" aria-label={t('aiAssistant.closeButton')} onClick={onClose}>
          <X className="size-3.5" />
        </Button>
      </header>
      {showHistory ? (
        <div className="flex min-h-0 flex-1 flex-col p-2">
          <AiConversationList selectedId={conversationId} onSelect={select} />
        </div>
      ) : (
        <AiChatPanel variant="widget" conversationId={conversationId} onConversationChange={setConversationId} />
      )}
    </aside>
  )
}
