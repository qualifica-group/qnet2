import { useMutation, useQueryClient } from '@tanstack/react-query'
import { createAiConversation, sendAiMessage } from '@/features/ai-assistant/api'
import { aiAssistantKeys } from '@/features/ai-assistant/query-keys'
import type {
  AiConversation,
  AiConversationDetail,
  AiTurn,
  SendAiMessageInput,
} from '@/features/ai-assistant/types'

export interface SendAiMessageVariables {
  input: SendAiMessageInput
  /** Existing conversation, or `null` to create one first. */
  conversationId: number | null
  /** Fired as soon as a new conversation exists, so the UI can select it. */
  onConversationCreated?: (conversation: AiConversation) => void
}

export interface SendAiMessageResult {
  conversationId: number
  turn: AiTurn
}

/**
 * Saves the message and queues the turn (spec 0433: 202, no stream). Resolves with
 * the queued turn, which the caller follows by polling; rejects with the HTTP error
 * so the composer can give draft, files and mentions back (nothing was saved).
 */
export function useSendAiMessage() {
  const queryClient = useQueryClient()

  const mutation = useMutation<SendAiMessageResult, Error, SendAiMessageVariables>({
    mutationFn: async ({ input, conversationId, onConversationCreated }) => {
      // Step 1: make sure a conversation exists
      let id = conversationId
      if (id === null) {
        const created = await createAiConversation()
        id = created.id
        queryClient.setQueryData<AiConversationDetail>(aiAssistantKeys.conversation(id), {
          ...created,
          messages: [],
        })
        onConversationCreated?.(created)
      }

      // Step 2: save the message and queue the turn
      const { user_message, turn } = await sendAiMessage(id, input)

      // Step 3: the message is saved for good (D-16): show it at once
      queryClient.setQueryData<AiConversationDetail>(aiAssistantKeys.conversation(id), (detail) =>
        detail ? { ...detail, messages: [...detail.messages, user_message] } : detail,
      )
      return { conversationId: id, turn }
    },
  })

  return {
    send: mutation.mutateAsync,
    reset: mutation.reset,
    isSending: mutation.isPending,
    error: mutation.error,
  }
}
