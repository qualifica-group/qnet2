import { Suspense, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronDown, ChevronUp, MessageCircle } from 'lucide-react'
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible'
import { useIsMobile } from '@/hooks/use-mobile'
import { cn } from '@/lib/utils'
import { useAiDockEntry } from '@/features/ai-assistant/hooks/use-ai-dock-entry'
import { onboardingAnchor } from '@/features/onboarding/steps'
import { lazyRoute } from '@/routes/lazy-route'
import { ChatChannelList } from '@/features/work-order-chat/dock/chat-channel-list'
import { ChatDockWindow } from '@/features/work-order-chat/dock/chat-dock-window'
import { ChatUnreadBadge } from '@/features/work-order-chat/dock/chat-unread-badge'
import { DockHeader } from '@/features/work-order-chat/dock/dock-header'
import {
  CHAT_DOCK_BODY_CLASS,
  CHAT_DOCK_BOX_CLASS,
  CHAT_DOCK_FULL_SCREEN_CLASS,
  CHAT_DOCK_HEADER_MUTED_CLASS,
} from '@/features/work-order-chat/dock/dock-styles'
import { useChatChannelsLive } from '@/features/work-order-chat/dock/use-chat-channels-live'
import { useChatDock } from '@/features/work-order-chat/dock/chat-dock-context'
import { AI_WINDOW_KEY, channelWindowKey, type ChatDockWindowKey } from '@/features/work-order-chat/dock/use-chat-dock-windows'
import { useChatEnabled } from '@/features/work-order-chat/use-chat-enabled'
import { useChatChannels } from '@/features/work-order-chat/use-work-order-chat'
import type { ChatChannel } from '@/features/work-order-chat/types'

const AiDockWindow = lazyRoute(() => import('@/features/ai-assistant/components/ai-dock-window'))

const NO_CHANNELS: ChatChannel[] = []

/**
 * The chat dock (spec 0432), like Reezl's: a low "Chat" bar at the bottom
 * right with the total unread, present whenever there is someone to talk to
 * (a channel, the chat being switched on, or the AI assistant; user directive
 * 2026-11-25); it opens the conversation list (the
 * AI assistant pinned on top, then the project work orders of the user's
 * team) and a click opens that conversation's window beside the bar. Below
 * Dialog/Sheet overlays (`z-50`); on a phone an open panel or window takes the
 * whole screen, above everything, one window at a time.
 */
export function ChatDock() {
  const { t } = useTranslation()
  const isMobile = useIsMobile()
  const aiAvailable = useAiDockEntry()
  const chatEnabled = useChatEnabled()
  const channelsQuery = useChatChannels(chatEnabled)
  const [panelOpen, setPanelOpen] = useState(false)
  const dock = useChatDock()
  useChatChannelsLive(channelsQuery.data)

  const channels = channelsQuery.data ?? NO_CHANNELS
  // The listed channels win over those opened from a work order (fresher unread and last message).
  const channelsByKey = new Map(
    [...(dock?.extraChannels ?? NO_CHANNELS), ...channels].map((channel) => [channelWindowKey(channel.work_order_id), channel]),
  )
  const totalUnread = channels.reduce((sum, channel) => sum + channel.unread_count, 0)
  const windows = dock?.windows ?? []
  const anyOpen = panelOpen || windows.some((window) => !window.minimized)
  const hasChannels = chatEnabled && channelsByKey.size > 0

  // Nothing to talk to (no channel, chat switched off, no AI assistant), or no provider.
  if (!dock || (!hasChannels && !aiAvailable)) {
    return null
  }

  const openWindow = (key: ChatDockWindowKey) => {
    dock.open(key)
    if (isMobile) {
      setPanelOpen(false)
    }
  }

  return (
    <section
      aria-label={t('workOrders.chat.dock.label')}
      className={cn('fixed right-4 bottom-0 z-40 flex items-end gap-2', anyOpen && 'max-sm:z-50')}
    >
      {windows.map((window) => {
        const windowProps = {
          minimized: window.minimized,
          onToggleMinimized: () => dock.toggleMinimized(window.key),
          onClose: () => dock.close(window.key),
        }
        if (window.key === AI_WINDOW_KEY) {
          return aiAvailable ? (
            <Suspense key={window.key} fallback={null}>
              <AiDockWindow {...windowProps} />
            </Suspense>
          ) : null
        }
        // A channel that left the list (removed from the team) closes its window.
        const channel = channelsByKey.get(window.key)
        return channel ? <ChatDockWindow key={window.key} channel={channel} {...windowProps} /> : null
      })}
      <Collapsible
        open={panelOpen}
        onOpenChange={setPanelOpen}
        className={cn(CHAT_DOCK_BOX_CLASS, panelOpen && cn(CHAT_DOCK_FULL_SCREEN_CLASS, 'sm:w-80'))}
        {...(aiAvailable ? onboardingAnchor('ai-assistant') : {})}
      >
        <DockHeader>
          <MessageCircle className="size-3.5 shrink-0" aria-hidden="true" />
          <span className="min-w-0 flex-1 truncate">{t('workOrders.chat.dock.bar')}</span>
          <ChatUnreadBadge count={totalUnread} />
          {panelOpen ? (
            <ChevronDown className={cn('size-3.5 shrink-0', CHAT_DOCK_HEADER_MUTED_CLASS)} aria-hidden="true" />
          ) : (
            <ChevronUp className={cn('size-3.5 shrink-0', CHAT_DOCK_HEADER_MUTED_CLASS)} aria-hidden="true" />
          )}
        </DockHeader>
        <CollapsibleContent className={CHAT_DOCK_BODY_CLASS}>
          <ChatChannelList
            showChannels={chatEnabled}
            channels={channelsQuery.data}
            isPending={channelsQuery.isPending}
            showAssistant={aiAvailable}
            onRetry={() => void channelsQuery.refetch()}
            onOpenAssistant={() => openWindow(AI_WINDOW_KEY)}
            onOpen={(workOrderId) => openWindow(channelWindowKey(workOrderId))}
          />
        </CollapsibleContent>
      </Collapsible>
    </section>
  )
}
