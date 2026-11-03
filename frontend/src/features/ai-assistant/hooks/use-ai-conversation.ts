import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchAiConversation } from '@/features/ai-assistant/api'
import { aiAssistantKeys } from '@/features/ai-assistant/query-keys'
import type { AiConversationDetail } from '@/features/ai-assistant/types'

/** One conversation with its messages; disabled while `id` is null (new chat). */
export function useAiConversation(id: number | null) {
  return useQuery<AiConversationDetail, AxiosError>({
    queryKey: aiAssistantKeys.conversation(id ?? 0),
    queryFn: () => fetchAiConversation(id as number),
    enabled: id !== null,
  })
}
