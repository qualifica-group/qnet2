import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  AiAssistantConfig,
  AiConversation,
  AiConversationDetail,
  AiConversationsPage,
  AiMessageStarted,
  AiTurn,
  SendAiMessageInput,
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

/** Builds the multipart body of the send endpoint (spec 0433 data_contract). */
export function buildAiMessageFormData(input: SendAiMessageInput): FormData {
  const form = new FormData()
  form.append('content', input.content)
  input.mentions.forEach((mention, index) => {
    form.append(`mentions[${index}][module]`, mention.module)
    form.append(`mentions[${index}][id]`, String(mention.id))
  })
  input.files.forEach((file) => form.append('files[]', file))
  if (input.pageGuideKey) {
    form.append('page_guide_key', input.pageGuideKey)
  }
  if (input.pageGuideText) {
    form.append('page_guide_text', input.pageGuideText)
  }
  form.append('locale', input.locale)
  return form
}

/** Saves the message and queues the turn (202); the answer is read later with `fetchAiTurn`. */
export async function sendAiMessage(conversationId: number, input: SendAiMessageInput): Promise<AiMessageStarted> {
  const { data } = await apiClient.post<ApiResponse<AiMessageStarted>>(
    `/ai-assistant/conversations/${conversationId}/messages`,
    buildAiMessageFormData(input),
  )
  return data.data
}

export async function fetchAiTurn(conversationId: number, turnId: number): Promise<AiTurn> {
  const { data } = await apiClient.get<ApiResponse<AiTurn>>(
    `/ai-assistant/conversations/${conversationId}/turns/${turnId}`,
  )
  return data.data
}

/** The server checks the preview belongs to this turn and person, confirms it and resumes the turn (202). */
export async function confirmAiPreview(conversationId: number, turnId: number, previewId: string): Promise<AiTurn> {
  const { data } = await apiClient.post<ApiResponse<AiTurn>>(
    `/ai-assistant/conversations/${conversationId}/turns/${turnId}/confirm`,
    { preview_id: previewId },
  )
  return data.data
}

/** Nothing is written: the turn resumes with a `cancelled` outcome. */
export async function cancelAiPreview(conversationId: number, turnId: number, previewId: string): Promise<AiTurn> {
  const { data } = await apiClient.post<ApiResponse<AiTurn>>(
    `/ai-assistant/conversations/${conversationId}/turns/${turnId}/cancel`,
    { preview_id: previewId },
  )
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
