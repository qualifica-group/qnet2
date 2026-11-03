import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  AiAssistantConfig,
  AiConversation,
  AiConversationDetail,
  AiConversationsPage,
} from '@/features/ai-assistant/types'

export const AI_CONVERSATIONS_PAGE_SIZE = 30

export async function fetchAiAssistantConfig(): Promise<AiAssistantConfig> {
  const { data } = await apiClient.get<ApiResponse<AiAssistantConfig>>('/ai-assistant/config')
  return data.data
}

/** The list endpoint answers the paginated payload directly, not the envelope. */
export async function fetchAiConversations(
  offset = 0,
  limit = AI_CONVERSATIONS_PAGE_SIZE,
): Promise<AiConversationsPage> {
  const { data } = await apiClient.get<AiConversationsPage>('/ai-assistant/conversations', {
    params: { offset, limit },
  })
  return data
}

export async function fetchAiConversation(id: number): Promise<AiConversationDetail> {
  const { data } = await apiClient.get<ApiResponse<AiConversationDetail>>(
    `/ai-assistant/conversations/${id}`,
  )
  return data.data
}

export async function createAiConversation(): Promise<AiConversation> {
  const { data } = await apiClient.post<ApiResponse<AiConversation>>('/ai-assistant/conversations', {})
  return data.data
}

export async function deleteAiConversation(id: number): Promise<void> {
  await apiClient.delete(`/ai-assistant/conversations/${id}`)
}

/** Authenticated download goes through the client (Bearer): fetch the blob, not a bare href. */
export async function downloadAiAttachment(messageId: number, attachmentId: number): Promise<Blob> {
  const { data } = await apiClient.get<Blob>(
    `/ai-assistant/messages/${messageId}/attachments/${attachmentId}`,
    { responseType: 'blob' },
  )
  return data
}
