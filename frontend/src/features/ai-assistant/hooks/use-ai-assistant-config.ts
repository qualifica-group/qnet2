import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchAiAssistantConfig } from '@/features/ai-assistant/api'
import { aiAssistantKeys } from '@/features/ai-assistant/query-keys'
import type { AiAssistantConfig } from '@/features/ai-assistant/types'

export function useAiAssistantConfig(options: { enabled?: boolean } = {}) {
  return useQuery<AiAssistantConfig, AxiosError>({
    queryKey: aiAssistantKeys.config,
    queryFn: fetchAiAssistantConfig,
    enabled: options.enabled ?? true,
    staleTime: 5 * 60 * 1000,
  })
}
