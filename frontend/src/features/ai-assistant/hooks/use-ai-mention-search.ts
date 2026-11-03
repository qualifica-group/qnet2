import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ForSelectItem } from '@/features/for-select/types'
import { aiAssistantKeys } from '@/features/ai-assistant/query-keys'
import { AI_MENTION_MIN_SEARCH_LENGTH, searchAiMentions } from '@/features/ai-assistant/mention-modules'
import type { AiMentionModule } from '@/features/ai-assistant/types'

/** Searches the for-select of `module`; inactive until 2 characters are typed (AC-022). */
export function useAiMentionSearch(module: AiMentionModule | null, term: string) {
  const trimmed = term.trim()
  return useQuery<ForSelectItem[], AxiosError>({
    queryKey: aiAssistantKeys.mentionSearch(module ?? '', trimmed),
    queryFn: () => searchAiMentions(module as AiMentionModule, trimmed),
    enabled: module !== null && trimmed.length >= AI_MENTION_MIN_SEARCH_LENGTH,
    staleTime: 30 * 1000,
  })
}
