// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, renderHook } from '@testing-library/react'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/features/ai-assistant/api', () => ({ createAiConversation: vi.fn(), sendAiMessage: vi.fn() }))

import { createAiConversation, sendAiMessage } from '@/features/ai-assistant/api'
import { useSendAiMessage } from '@/features/ai-assistant/hooks/use-send-ai-message'
import { aiAssistantKeys } from '@/features/ai-assistant/query-keys'
import type {
  AiConversation,
  AiConversationDetail,
  AiMessage,
  AiTurn,
  SendAiMessageInput,
} from '@/features/ai-assistant/types'

const conversation: AiConversation = { id: 1, title: '', last_message_at: null, created_at: 'now' }
const userMessage: AiMessage = {
  id: 10, role: 'user', content: 'hi', mentions: [], attachments: [],
  model: null, input_tokens: null, output_tokens: null, created_at: 'now',
}
const queuedTurn: AiTurn = {
  id: 5, conversation_id: 1, status: 'queued', user_message_id: 10, assistant_text: '', assistant_message: null,
  steps: [], preview: null, queue_position: null, error_code: null, error_message: null,
  created_at: 'now', updated_at: 'now', finished_at: null,
}
const input: SendAiMessageInput = {
  content: 'hi', mentions: [], files: [], pageGuideKey: null, pageGuideText: null, locale: 'it',
}

function setup() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  )
  const view = renderHook(() => useSendAiMessage(), { wrapper })
  return { queryClient, ...view }
}

const cached = (queryClient: QueryClient) =>
  queryClient.getQueryData<AiConversationDetail>(aiAssistantKeys.conversation(1))

// REQUIREMENT CHANGED (spec 0433 D-15, ticket 04): the POST answers 202 with the queued turn;
// no SSE stream is read, so the old stream-event tests are replaced by these.
describe('useSendAiMessage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(createAiConversation).mockResolvedValue(conversation)
    vi.mocked(sendAiMessage).mockResolvedValue({ user_message: userMessage, turn: queuedTurn })
  })

  it('creates the conversation, stores the saved message and returns the queued turn', async () => {
    const onCreated = vi.fn()
    const { result, queryClient } = setup()

    let started
    await act(async () => {
      started = await result.current.send({ input, conversationId: null, onConversationCreated: onCreated })
    })

    expect(onCreated).toHaveBeenCalledWith(conversation)
    expect(sendAiMessage).toHaveBeenCalledWith(1, input)
    expect(started).toEqual({ conversationId: 1, turn: queuedTurn })
    expect(cached(queryClient)?.messages.map((m) => m.id)).toEqual([10])
  })

  it('does not create a conversation for an existing one', async () => {
    const { result, queryClient } = setup()
    queryClient.setQueryData(aiAssistantKeys.conversation(1), { ...conversation, messages: [] })

    await act(async () => {
      await result.current.send({ input, conversationId: 1 })
    })

    expect(createAiConversation).not.toHaveBeenCalled()
    expect(cached(queryClient)?.messages).toHaveLength(1)
  })

  it('rejects when the POST fails and leaves the cache untouched, so the composer restores the draft', async () => {
    vi.mocked(sendAiMessage).mockRejectedValue(new Error('409'))
    const { result, queryClient } = setup()
    queryClient.setQueryData(aiAssistantKeys.conversation(1), { ...conversation, messages: [] })

    await act(async () => {
      await expect(result.current.send({ input, conversationId: 1 })).rejects.toThrow('409')
    })

    expect(cached(queryClient)?.messages).toEqual([])
  })
})
