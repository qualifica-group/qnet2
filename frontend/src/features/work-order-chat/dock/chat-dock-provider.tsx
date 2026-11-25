import { useState, type ReactNode } from 'react'
import { useIsMobile } from '@/hooks/use-mobile'
import { ChatDockContext } from '@/features/work-order-chat/dock/chat-dock-context'
import { channelWindowKey, useChatDockWindows, type ChatDockWindowKey } from '@/features/work-order-chat/dock/use-chat-dock-windows'
import type { ChatChannel } from '@/features/work-order-chat/types'

const NO_EXTRA_CHANNELS: ChatChannel[] = []

/**
 * Holds the chat dock's windows for the whole app (spec 0432), so a page (the
 * work order's Chat tab) can open a conversation in the dock mounted by the
 * layout.
 */
export function ChatDockProvider({ children }: { children: ReactNode }) {
  const isMobile = useIsMobile()
  const windows = useChatDockWindows()
  const [extraChannels, setExtraChannels] = useState(NO_EXTRA_CHANNELS)

  const open = (key: ChatDockWindowKey) => windows.open(key, isMobile)

  const openChannel = (channel: ChatChannel) => {
    setExtraChannels((current) => [
      ...current.filter((known) => known.work_order_id !== channel.work_order_id),
      channel,
    ])
    open(channelWindowKey(channel.work_order_id))
  }

  return (
    <ChatDockContext.Provider
      value={{
        windows: windows.windows,
        open,
        toggleMinimized: windows.toggleMinimized,
        close: windows.close,
        openChannel,
        extraChannels,
      }}
    >
      {children}
    </ChatDockContext.Provider>
  )
}
