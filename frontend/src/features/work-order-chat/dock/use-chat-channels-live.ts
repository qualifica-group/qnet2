import { useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { getEcho, onEchoReconnect } from '@/lib/echo'
import { useAuth } from '@/features/auth/use-auth'
import { applyChatEvent } from '@/features/work-order-chat/chat-cache'
import { workOrderChatKeys } from '@/features/work-order-chat/query-keys'
import type { ChatChannel, ChatEventPayload, ChatThread } from '@/features/work-order-chat/types'

const CHAT_EVENT = '.chat.message'

type Subscription = Pick<ChatChannel, 'work_order_id' | 'channel'>

/**
 * The dock's realtime (spec 0432 D-5), the ONLY subscriber of the chat
 * channels: Echo's `leave` drops a channel for every listener, so a second
 * owner would cut this one off. Listens to the private channel of every
 * listed work order (names as received, never rebuilt): an event updates that
 * thread's cache, if loaded, and rereads the channel list (badges, previews,
 * order). On reconnect, events fired while offline are lost: list and threads
 * are reloaded.
 */
export function useChatChannelsLive(channels: ChatChannel[] | undefined): void {
  const queryClient = useQueryClient()
  const { user } = useAuth()
  const currentUserId = user?.id ?? null
  // A stable key: a refetch with the same channels must not resubscribe.
  const subscriptionsKey = JSON.stringify(
    (channels ?? []).map(({ work_order_id, channel }): Subscription => ({ work_order_id, channel })),
  )

  useEffect(() => {
    const subscriptions = JSON.parse(subscriptionsKey) as Subscription[]
    if (subscriptions.length === 0) {
      return undefined
    }
    const echo = getEcho()
    const refreshChannels = () => void queryClient.invalidateQueries({ queryKey: workOrderChatKeys.channels() })

    for (const { work_order_id: workOrderId, channel } of subscriptions) {
      echo.private(channel).listen(CHAT_EVENT, (event: ChatEventPayload) => {
        queryClient.setQueryData<ChatThread>(workOrderChatKeys.thread(workOrderId), (thread) =>
          thread ? applyChatEvent(thread, event, currentUserId) : thread,
        )
        refreshChannels()
      })
    }
    const stopReconnect = onEchoReconnect(() => {
      refreshChannels()
      for (const { work_order_id: workOrderId } of subscriptions) {
        void queryClient.invalidateQueries({ queryKey: workOrderChatKeys.thread(workOrderId) })
      }
    })

    return () => {
      stopReconnect()
      for (const { channel } of subscriptions) {
        echo.leave(channel)
      }
    }
  }, [queryClient, subscriptionsKey, currentUserId])
}
