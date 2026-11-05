import { useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { getEcho, onEchoReconnect } from '@/lib/echo'
import { useAuth } from '@/features/auth/use-auth'
import { applyChatEvent } from '@/features/work-order-chat/chat-cache'
import { workOrderChatKeys } from '@/features/work-order-chat/query-keys'
import type { ChatEventPayload, ChatThread } from '@/features/work-order-chat/types'

const CHAT_EVENT = '.chat.message'

/**
 * Keeps the cached thread live: listens to the private channel received from the
 * backend (never rebuilt here) and reloads the thread when the socket reconnects,
 * since events fired while offline are lost. Pass `null` until the thread (and so
 * the channel name) is known. Mount it ONCE per work order: it leaves the channel on cleanup.
 */
export function useChatChannel(workOrderId: number, channel: string | null): void {
  const queryClient = useQueryClient()
  const { user } = useAuth()
  const currentUserId = user?.id ?? null

  useEffect(() => {
    if (!channel) {
      return undefined
    }
    const queryKey = workOrderChatKeys.thread(workOrderId)
    const echo = getEcho()
    echo.private(channel).listen(CHAT_EVENT, (event: ChatEventPayload) => {
      queryClient.setQueryData<ChatThread>(queryKey, (thread) =>
        thread ? applyChatEvent(thread, event, currentUserId) : thread,
      )
    })
    const stopReconnect = onEchoReconnect(() => {
      void queryClient.invalidateQueries({ queryKey })
    })

    return () => {
      stopReconnect()
      echo.leave(channel)
    }
  }, [queryClient, workOrderId, channel, currentUserId])
}
