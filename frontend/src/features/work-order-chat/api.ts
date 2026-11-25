import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  ChatChannel,
  ChatListParams,
  ChatMessage,
  ChatReadResult,
  ChatThread,
} from '@/features/work-order-chat/types'

const chatPath = (workOrderId: number) => `/work-orders/${workOrderId}/chat`

export async function fetchChatMessages(workOrderId: number, params?: ChatListParams): Promise<ChatThread> {
  const { data } = await apiClient.get<ApiResponse<ChatThread>>(`${chatPath(workOrderId)}/messages`, { params })
  return data.data
}

export async function sendChatMessage(workOrderId: number, body: string): Promise<ChatMessage> {
  const { data } = await apiClient.post<ApiResponse<ChatMessage>>(`${chatPath(workOrderId)}/messages`, { body })
  return data.data
}

export async function editChatMessage(workOrderId: number, messageId: number, body: string): Promise<ChatMessage> {
  const { data } = await apiClient.patch<ApiResponse<ChatMessage>>(
    `${chatPath(workOrderId)}/messages/${messageId}`,
    { body },
  )
  return data.data
}

export async function deleteChatMessage(workOrderId: number, messageId: number): Promise<ChatMessage> {
  const { data } = await apiClient.delete<ApiResponse<ChatMessage>>(`${chatPath(workOrderId)}/messages/${messageId}`)
  return data.data
}

export async function markChatRead(workOrderId: number, lastMessageId: number): Promise<ChatReadResult> {
  const { data } = await apiClient.post<ApiResponse<ChatReadResult>>(`${chatPath(workOrderId)}/read`, {
    last_message_id: lastMessageId,
  })
  return data.data
}

/** The chat dock's channels (spec 0432): the actor's project work orders, newest message first. */
export async function fetchChatChannels(): Promise<ChatChannel[]> {
  const { data } = await apiClient.get<ApiResponse<ChatChannel[]>>('/work-order-chat/channels')
  return data.data
}
