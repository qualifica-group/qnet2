import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { SupportRequestResult, SupportRequestTopic } from '@/features/support-requests/types'

/** Active problem types for the dialog (any authenticated user). */
export async function fetchSupportRequestTopics(): Promise<SupportRequestTopic[]> {
  const { data } = await apiClient.get<ApiResponse<SupportRequestTopic[]>>('/support-requests/topics')
  return data.data
}

/** Sends the multipart request; axios sets the multipart boundary itself. */
export async function submitSupportRequest(body: FormData): Promise<SupportRequestResult> {
  const { data } = await apiClient.post<ApiResponse<SupportRequestResult>>('/support-requests', body)
  return data.data
}
