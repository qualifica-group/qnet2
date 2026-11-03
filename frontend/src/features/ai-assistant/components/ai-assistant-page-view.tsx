import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useLocation } from 'react-router-dom'
import { Menu } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { AiChatPanel } from '@/features/ai-assistant/components/ai-chat-panel'
import { AiConversationList } from '@/features/ai-assistant/components/ai-conversation-list'

/** The widget hands over its conversation through the router state when opening the full page. */
function readInitialConversationId(state: unknown): number | null {
  if (typeof state === 'object' && state !== null && 'conversationId' in state) {
    const { conversationId } = state as { conversationId: unknown }
    return typeof conversationId === 'number' ? conversationId : null
  }
  return null
}

/**
 * Full-page assistant: conversation list on the left from `md`, in a sheet
 * below it; the chat takes the rest and always sends the `general` guide (AC-024).
 */
export function AiAssistantPageView() {
  const { t } = useTranslation()
  const { state } = useLocation()
  const [conversationId, setConversationId] = useState<number | null>(() => readInitialConversationId(state))
  const [listOpen, setListOpen] = useState(false)

  const select = (id: number | null) => {
    setConversationId(id)
    setListOpen(false)
  }

  return (
    <div className="flex h-[calc(100svh-6rem)] min-h-[28rem] flex-1 flex-col gap-3">
      <div className="flex items-center gap-2">
        <h1 className="min-w-0 flex-1 truncate text-xl font-semibold">{t('aiAssistant.title')}</h1>
        <Button type="button" variant="outline" size="sm" className="md:hidden" onClick={() => setListOpen(true)}>
          <Menu className="size-3.5" />
          {t('aiAssistant.showConversations')}
        </Button>
      </div>
      <div className="flex min-h-0 flex-1 gap-3">
        <aside className="hidden min-h-0 w-64 shrink-0 flex-col rounded-lg border border-border bg-surface p-2 md:flex">
          <AiConversationList selectedId={conversationId} onSelect={select} />
        </aside>
        <AiChatPanel
          variant="page"
          conversationId={conversationId}
          onConversationChange={setConversationId}
          forceGeneralGuide
          className="min-w-0 rounded-lg border border-border"
        />
      </div>
      <Sheet open={listOpen} onOpenChange={setListOpen}>
        <SheetContent side="left" resizable={false} aria-describedby={undefined} className="flex w-72 max-w-[85vw] flex-col gap-2 p-3">
          <SheetHeader className="p-0">
            <SheetTitle>{t('aiAssistant.conversations')}</SheetTitle>
          </SheetHeader>
          <AiConversationList selectedId={conversationId} onSelect={select} />
        </SheetContent>
      </Sheet>
    </div>
  )
}
