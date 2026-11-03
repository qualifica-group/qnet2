import { useInfiniteQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { AI_CONVERSATIONS_PAGE_SIZE, fetchAiConversations } from '@/features/ai-assistant/api'
import { aiAssistantKeys } from '@/features/ai-assistant/query-keys'
import type { AiConversationsPage } from '@/features/ai-assistant/types'

/** Paginated conversation list (offset/limit); `fetchNextPage` loads older ones. */
export function useAiConversations(options: { enabled?: boolean } = {}) {
  return useInfiniteQuery<AiConversationsPage, AxiosError>({
    queryKey: aiAssistantKeys.conversations,
    queryFn: ({ pageParam }) => fetchAiConversations(pageParam as number, AI_CONVERSATIONS_PAGE_SIZE),
    initialPageParam: 0,
    getNextPageParam: ({ pagination }) => {
      const next = pagination.offset + pagination.limit
      return next < pagination.total ? next : undefined
    },
    enabled: options.enabled ?? true,
  })
}
