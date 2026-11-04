import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { toast } from 'sonner'
import { useTranslation } from 'react-i18next'
import type { ApiErrorResponse } from '@/api/types'
import {
  deleteChatMessage,
  editChatMessage,
  fetchChatMessages,
  markChatRead,
  sendChatMessage,
} from '@/features/work-order-chat/api'
import { applyChatEvent, applyReadResult, prependOlder } from '@/features/work-order-chat/chat-cache'
import { workOrderChatKeys } from '@/features/work-order-chat/query-keys'
import type { ChatEventAction, ChatMessage, ChatReadResult, ChatThread } from '@/features/work-order-chat/types'
import { useAuth } from '@/features/auth/use-auth'

type ChatError = AxiosError<ApiErrorResponse>

/** Latest page of the thread plus the permission flags and the unread counter. */
export function useWorkOrderChat(workOrderId: number) {
  return useQuery<ChatThread, ChatError>({
    queryKey: workOrderChatKeys.thread(workOrderId),
    queryFn: () => fetchChatMessages(workOrderId),
  })
}

/** Fetches the page before the oldest loaded message and merges it into the cached thread. */
export function useLoadOlderChatMessages(workOrderId: number) {
  const queryClient = useQueryClient()
  return useMutation<ChatThread, ChatError, number>({
    mutationFn: (beforeId) => fetchChatMessages(workOrderId, { before_id: beforeId }),
    onSuccess: (older) => {
      queryClient.setQueryData<ChatThread>(workOrderChatKeys.thread(workOrderId), (thread) =>
        thread ? prependOlder(thread, older) : thread,
      )
    },
  })
}

function useWriteMutation<TVariables>(
  workOrderId: number,
  action: ChatEventAction,
  request: (variables: TVariables) => Promise<ChatMessage>,
) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { user } = useAuth()
  return useMutation<ChatMessage, ChatError, TVariables>({
    mutationFn: request,
    onSuccess: (message) => {
      queryClient.setQueryData<ChatThread>(workOrderChatKeys.thread(workOrderId), (thread) =>
        thread ? applyChatEvent(thread, { action, message }, user?.id ?? null) : thread,
      )
    },
    onError: (error) => {
      toast.error(error.response?.data?.message ?? t('workOrders.chat.actionError'))
    },
  })
}

export function useSendChatMessage(workOrderId: number) {
  return useWriteMutation<string>(workOrderId, 'created', (body) => sendChatMessage(workOrderId, body))
}

export function useEditChatMessage(workOrderId: number) {
  return useWriteMutation<{ messageId: number; body: string }>(workOrderId, 'updated', ({ messageId, body }) =>
    editChatMessage(workOrderId, messageId, body),
  )
}

export function useDeleteChatMessage(workOrderId: number) {
  return useWriteMutation<number>(workOrderId, 'deleted', (messageId) => deleteChatMessage(workOrderId, messageId))
}

export function useMarkChatRead(workOrderId: number) {
  const queryClient = useQueryClient()
  return useMutation<ChatReadResult, ChatError, number>({
    mutationFn: (lastMessageId) => markChatRead(workOrderId, lastMessageId),
    onSuccess: (result) => {
      queryClient.setQueryData<ChatThread>(workOrderChatKeys.thread(workOrderId), (thread) =>
        thread ? applyReadResult(thread, result) : thread,
      )
    },
  })
}
