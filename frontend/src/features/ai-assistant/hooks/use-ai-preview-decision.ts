import { useRef } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { cancelAiPreview, confirmAiPreview } from '@/features/ai-assistant/api'
import { aiAssistantKeys } from '@/features/ai-assistant/query-keys'
import type { AiTurn } from '@/features/ai-assistant/types'

export type AiPreviewDecision = 'confirm' | 'cancel'

/**
 * Confirm or Cancel of the open preview (0433): only the preview id travels. On success the
 * resumed turn (`queued`) replaces the cached one, so the card goes away and the normal
 * polling restarts. On a refusal the turn is read again: the server says what the preview became.
 */
export function useAiPreviewDecision(conversationId: number, turnId: number, previewId: string) {
  const queryClient = useQueryClient()
  const turnKey = aiAssistantKeys.turn(conversationId, turnId)

  const mutation = useMutation<AiTurn, AxiosError, AiPreviewDecision>({
    mutationFn: (decision) =>
      decision === 'confirm'
        ? confirmAiPreview(conversationId, turnId, previewId)
        : cancelAiPreview(conversationId, turnId, previewId),
    onSuccess: (resumed) => queryClient.setQueryData(turnKey, resumed),
    onError: () => queryClient.invalidateQueries({ queryKey: turnKey }),
  })

  // The mutation state reaches the button one tick later: the ref closes that gap against a double click.
  const sent = useRef(false)
  const decide = (decision: AiPreviewDecision) => {
    if (sent.current) {
      return
    }
    sent.current = true
    mutation.mutate(decision, { onError: () => (sent.current = false) })
  }

  return {
    decide,
    /** Stays true after success: the card is about to disappear and a second click must do nothing. */
    isLocked: mutation.isPending || mutation.isSuccess,
    error: mutation.error,
  }
}
