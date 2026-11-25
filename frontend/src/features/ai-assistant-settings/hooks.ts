import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import {
  fetchAiAssistantSettings,
  fetchAiModelProviders,
  fetchAiModels,
  updateAiAssistantSettings,
} from '@/features/ai-assistant-settings/api'
import type {
  AiAssistantSettings,
  AiModelProvider,
  AiModelsPage,
  UpdateAiAssistantSettingsPayload,
} from '@/features/ai-assistant-settings/types'

export const aiSettingsKeys = {
  settings: ['ai-assistant-settings', 'settings'] as const,
  models: (search: string, limit: number) => ['ai-assistant-settings', 'models', search, limit] as const,
  providers: (modelId: string) => ['ai-assistant-settings', 'providers', modelId] as const,
}

/** Lists are cached by the backend for a few minutes: no point in refetching sooner. */
const CATALOG_STALE_MS = 60_000

export function useAiAssistantSettings() {
  return useQuery<AiAssistantSettings, AxiosError>({
    queryKey: aiSettingsKeys.settings,
    queryFn: fetchAiAssistantSettings,
  })
}

export function useUpdateAiAssistantSettings() {
  const queryClient = useQueryClient()

  return useMutation<AiAssistantSettings, AxiosError, UpdateAiAssistantSettingsPayload>({
    mutationFn: updateAiAssistantSettings,
    onSuccess: (saved) => {
      queryClient.setQueryData(aiSettingsKeys.settings, saved)
      // The `selected` flag of the model list follows the saved model.
      void queryClient.invalidateQueries({ queryKey: ['ai-assistant-settings', 'models'] })
    },
  })
}

export function useAiModels(search: string, limit: number) {
  return useQuery<AiModelsPage, AxiosError>({
    queryKey: aiSettingsKeys.models(search, limit),
    queryFn: () => fetchAiModels(search, limit),
    staleTime: CATALOG_STALE_MS,
    placeholderData: keepPreviousData,
  })
}

export function useAiModelProviders(modelId: string) {
  return useQuery<AiModelProvider[], AxiosError>({
    queryKey: aiSettingsKeys.providers(modelId),
    queryFn: () => fetchAiModelProviders(modelId),
    staleTime: CATALOG_STALE_MS,
    enabled: modelId !== '',
  })
}
