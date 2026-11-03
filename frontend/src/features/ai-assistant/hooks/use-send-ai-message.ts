import { useCallback, useEffect, useRef, useState } from 'react'
import { useMutation, useQueryClient, type QueryClient } from '@tanstack/react-query'
import { createAiConversation } from '@/features/ai-assistant/api'
import { AI_STREAM_INTERRUPTED, AiStreamError, streamAiMessage } from '@/features/ai-assistant/ai-sse-stream'
import { aiAssistantKeys } from '@/features/ai-assistant/query-keys'
import type {
  AiConversation,
  AiConversationDetail,
  AiStreamEvent,
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
  assistantText: string
}

function patchConversation(
  queryClient: QueryClient,
  id: number,
  patch: (detail: AiConversationDetail) => AiConversationDetail,
): void {
  queryClient.setQueryData<AiConversationDetail>(aiAssistantKeys.conversation(id), (detail) =>
    detail ? patch(detail) : detail,
  )
}

/** Applies one SSE event to the TanStack cache. */
function applyEvent(queryClient: QueryClient, conversationId: number, event: AiStreamEvent): void {
  if (event.event === 'message.created') {
    patchConversation(queryClient, conversationId, (detail) => ({
      ...detail,
      messages: [...detail.messages, event.data.user_message],
    }))
  } else if (event.event === 'message.completed') {
    const { assistant_message, conversation } = event.data
    patchConversation(queryClient, conversationId, (detail) => ({
      ...detail,
      ...conversation,
      messages: [...detail.messages, assistant_message],
    }))
  }
}

/** The server removed the user message on failure (D-13): drop it from the cache too. */
function dropUserMessage(queryClient: QueryClient, conversationId: number, messageId: number | null): void {
  if (messageId === null) {
    return
  }
  patchConversation(queryClient, conversationId, (detail) => ({
    ...detail,
    messages: detail.messages.filter((message) => message.id !== messageId),
  }))
}

/**
 * Sends a message and streams the reply. Resolves when the stream completes;
 * rejects with `AiStreamHttpError` (before the stream) or `AiStreamError`
 * (`error` event) so the caller can restore draft, files and mentions
 * (D-13/AC-021): nothing is lost from the cache either.
 */
export function useSendAiMessage() {
  const queryClient = useQueryClient()
  const abortRef = useRef<AbortController | null>(null)
  const [streamingText, setStreamingText] = useState('')

  useEffect(() => () => abortRef.current?.abort(), [])

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

      // Step 2: open the stream
      const controller = new AbortController()
      abortRef.current = controller
      setStreamingText('')
      let text = ''
      let userMessageId: number | null = null
      let completed = false

      // Step 3: fold events into text and cache
      for await (const event of streamAiMessage(id, input, controller.signal)) {
        if (event.event === 'error') {
          dropUserMessage(queryClient, id, userMessageId)
          throw new AiStreamError(event.data.message)
        }
        if (event.event === 'message.created') {
          userMessageId = event.data.user_message.id
        }
        if (event.event === 'delta') {
          text += event.data.text
          setStreamingText(text)
        }
        completed = completed || event.event === 'message.completed'
        applyEvent(queryClient, id, event)
      }

      // Step 4: a stream closed without a final event is an error; the server is the truth
      if (!completed) {
        await Promise.all([
          queryClient.invalidateQueries({ queryKey: aiAssistantKeys.conversation(id) }),
          queryClient.invalidateQueries({ queryKey: aiAssistantKeys.conversations }),
        ])
        throw new AiStreamError(AI_STREAM_INTERRUPTED)
      }
      return { conversationId: id, assistantText: text }
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: aiAssistantKeys.conversations }),
    onSettled: () => {
      abortRef.current = null
      setStreamingText('')
    },
  })

  const cancel = useCallback(() => abortRef.current?.abort(), [])

  return {
    send: mutation.mutateAsync,
    cancel,
    reset: mutation.reset,
    /** Assistant reply accumulated so far; empty when idle. */
    streamingText,
    isSending: mutation.isPending,
    error: mutation.error,
  }
}
