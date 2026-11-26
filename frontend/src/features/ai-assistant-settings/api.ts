import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  AiAssistantSettings,
  AiModelProvider,
  AiModelsPage,
  UpdateAiAssistantSettingsPayload,
} from '@/features/ai-assistant-settings/types'

/** The browser only talks to QNET2: the backend reads OpenRouter and caches the lists. */
export async function fetchAiAssistantSettings(): Promise<AiAssistantSettings> {
  const { data } = await apiClient.get<ApiResponse<AiAssistantSettings>>('/ai-assistant/settings')
  return data.data
}

export async function updateAiAssistantSettings(
  payload: UpdateAiAssistantSettingsPayload,
): Promise<AiAssistantSettings> {
  const { data } = await apiClient.put<ApiResponse<AiAssistantSettings>>('/ai-assistant/settings', payload)
  return data.data
}

/** The list endpoint answers the paginated payload directly, not the envelope. */
export async function fetchAiModels(search: string, limit: number): Promise<AiModelsPage> {
  const { data } = await apiClient.get<AiModelsPage>('/ai-assistant/models', {
    params: { search: search || undefined, offset: 0, limit },
  })
  return data
}

export async function fetchAiModelProviders(modelId: string): Promise<AiModelProvider[]> {
  const { data } = await apiClient.get<ApiResponse<AiModelProvider[]>>('/ai-assistant/models/providers', {
    params: { model_id: modelId },
  })
  return data.data
}
