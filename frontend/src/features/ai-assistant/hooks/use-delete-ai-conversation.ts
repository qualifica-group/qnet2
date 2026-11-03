import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { deleteAiConversation } from '@/features/ai-assistant/api'
import { aiAssistantKeys } from '@/features/ai-assistant/query-keys'

export function useDeleteAiConversation() {
  const queryClient = useQueryClient()
  return useMutation<void, AxiosError, number>({
    mutationFn: deleteAiConversation,
    onSuccess: (_data, id) => {
      queryClient.removeQueries({ queryKey: aiAssistantKeys.conversation(id) })
      return queryClient.invalidateQueries({ queryKey: aiAssistantKeys.conversations })
    },
  })
}
