import { useEffect, useSyncExternalStore } from 'react'
import { useMarkChatRead } from '@/features/work-order-chat/use-work-order-chat'
import type { ChatThread } from '@/features/work-order-chat/types'

function subscribeVisibility(callback: () => void): () => void {
  document.addEventListener('visibilitychange', callback)
  return () => document.removeEventListener('visibilitychange', callback)
}

const isDocumentVisible = () => document.visibilityState === 'visible'

/**
 * Marks the thread read up to its last message while the panel is mounted (the
 * tab is open) and the page is visible. Runs on load and whenever a newer
 * message lands; the response resets the unread counter in the cache.
 */
export function useChatMarkRead(workOrderId: number, thread: ChatThread | undefined): void {
  const { mutate } = useMarkChatRead(workOrderId)
  const isVisible = useSyncExternalStore(subscribeVisibility, isDocumentVisible, () => true)
  const lastMessageId = thread?.messages.at(-1)?.id ?? null
  const lastReadId = thread?.last_read_message_id ?? null

  useEffect(() => {
    if (isVisible && lastMessageId !== null && (lastReadId === null || lastMessageId > lastReadId)) {
      mutate(lastMessageId)
    }
  }, [isVisible, lastMessageId, lastReadId, mutate])
}
