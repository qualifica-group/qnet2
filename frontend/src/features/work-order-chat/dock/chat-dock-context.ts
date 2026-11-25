import { createContext, useContext } from 'react'
import type { ChatDockWindow, ChatDockWindowKey } from '@/features/work-order-chat/dock/use-chat-dock-windows'
import type { ChatChannel } from '@/features/work-order-chat/types'

export interface ChatDockContextValue {
  windows: ChatDockWindow[]
  /** Opens (or brings up) a conversation window; on a phone it is the only one. */
  open: (key: ChatDockWindowKey) => void
  toggleMinimized: (key: ChatDockWindowKey) => void
  close: (key: ChatDockWindowKey) => void
  /**
   * Opens a channel from outside the dock (the work order's Chat tab). The
   * channel is remembered, so its window opens even when the work order is
   * not in the user's list (a super-admin outside the team, spec 0359 D-4).
   */
  openChannel: (channel: ChatChannel) => void
  /** Channels opened from outside the dock that its list does not hold. */
  extraChannels: ChatChannel[]
}

export const ChatDockContext = createContext<ChatDockContextValue | null>(null)

/** The dock's state; null outside `ChatDockProvider` (the chat is then simply not openable). */
export function useChatDock(): ChatDockContextValue | null {
  return useContext(ChatDockContext)
}
