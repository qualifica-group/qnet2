import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchAiTurn } from '@/features/ai-assistant/api'
import { aiAssistantKeys } from '@/features/ai-assistant/query-keys'
import { isAiTurnAwaitingConfirmation, isAiTurnFinal, isAiTurnPolling } from '@/features/ai-assistant/turn-status'
import type { AiTurn } from '@/features/ai-assistant/types'

/** Used only when `/config` carries no `limits.poll_interval_ms`. */
export const AI_TURN_POLL_FALLBACK_MS = 1500

/** While the turn waits for Confirm or Cancel the read is this many times slower. */
export const AI_TURN_AWAITING_POLL_FACTOR = 4

interface TrackedTurn {
  conversationId: number
  turnId: number
}

export interface UseAiActiveTurnOptions {
  conversationId: number | null
  /** `active_turn` of the conversation detail: a reloaded page resumes from it. */
  serverTurn: AiTurn | null | undefined
  pollIntervalMs: number | undefined
}

/**
 * The turn the chat is following: started here (`track`) or resumed from the
 * conversation detail. It is read at `poll_interval_ms` while `queued` or
 * `running`, and stays available once closed so the outcome can be shown.
 */
export function useAiActiveTurn({ conversationId, serverTurn, pollIntervalMs }: UseAiActiveTurnOptions) {
  const queryClient = useQueryClient()
  const [tracked, setTracked] = useState<TrackedTurn | null>(null)

  // Adopt the turn the server lists as active when this browser is not following a newer one.
  const mustAdopt =
    conversationId !== null &&
    serverTurn != null &&
    (tracked === null || tracked.conversationId !== conversationId || serverTurn.id > tracked.turnId)
  if (mustAdopt) {
    setTracked({ conversationId, turnId: serverTurn.id })
  }

  const followed = tracked && tracked.conversationId === conversationId ? tracked.turnId : null
  const turnId = followed ?? (mustAdopt ? serverTurn.id : null)

  const query = useQuery<AiTurn, AxiosError>({
    queryKey: aiAssistantKeys.turn(conversationId ?? 0, turnId ?? 0),
    enabled: conversationId !== null && turnId !== null,
    initialData: serverTurn && serverTurn.id === turnId ? serverTurn : undefined,
    queryFn: async () => {
      const turn = await fetchAiTurn(conversationId as number, turnId as number)
      if (isAiTurnFinal(turn.status)) {
        // The closed turn has a saved assistant message and a title: refresh what lists them.
        void queryClient.invalidateQueries({ queryKey: aiAssistantKeys.conversation(turn.conversation_id) })
        void queryClient.invalidateQueries({ queryKey: aiAssistantKeys.conversations })
      }
      return turn
    },
    refetchInterval: (current) => {
      const status = current.state.data?.status
      const interval = pollIntervalMs ?? AI_TURN_POLL_FALLBACK_MS
      if (status === undefined || isAiTurnPolling(status)) {
        return interval
      }
      return isAiTurnAwaitingConfirmation(status) ? interval * AI_TURN_AWAITING_POLL_FACTOR : false
    },
  })

  /** Starts following a turn just queued by the POST. */
  const track = (turn: AiTurn) => {
    queryClient.setQueryData(aiAssistantKeys.turn(turn.conversation_id, turn.id), turn)
    setTracked({ conversationId: turn.conversation_id, turnId: turn.id })
  }

  return { turn: turnId === null ? null : (query.data ?? null), track }
}
