// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, renderHook, waitFor } from '@testing-library/react'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/features/ai-assistant/api', () => ({ createAiConversation: vi.fn() }))
vi.mock('@/features/ai-assistant/ai-sse-stream', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/ai-assistant/ai-sse-stream')>()),
  streamAiMessage: vi.fn(),
}))

import { createAiConversation } from '@/features/ai-assistant/api'
import { AI_STREAM_INTERRUPTED, AiStreamError, AiStreamHttpError, streamAiMessage } from '@/features/ai-assistant/ai-sse-stream'
import { useSendAiMessage } from '@/features/ai-assistant/hooks/use-send-ai-message'
import { aiAssistantKeys } from '@/features/ai-assistant/query-keys'
import type {
  AiConversation,
  AiConversationDetail,
  AiMessage,
  AiStreamEvent,
  SendAiMessageInput,
} from '@/features/ai-assistant/types'

const conversation: AiConversation = { id: 1, title: '', last_message_at: null, created_at: 'now' }
const userMessage: AiMessage = {
  id: 10, role: 'user', content: 'hi', mentions: [], attachments: [],
  model: null, input_tokens: null, output_tokens: null, created_at: 'now',
}
const assistantMessage: AiMessage = { ...userMessage, id: 11, role: 'assistant', content: 'Ciao!' }
const input: SendAiMessageInput = {
  content: 'hi', mentions: [], files: [], pageGuideKey: null, pageGuideText: null, locale: 'it',
}

function stub(events: AiStreamEvent[], failure?: unknown, gate?: Promise<void>) {
  vi.mocked(streamAiMessage).mockImplementation(async function* () {
    for (const event of events) {
      yield event
      if (event.event === 'delta') {
        await gate
      }
    }
    if (failure) {
      throw failure
    }
  })
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

describe('useSendAiMessage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(createAiConversation).mockResolvedValue(conversation)
  })

  it('creates the conversation, grows the text and stores both messages (AC-020)', async () => {
    let release!: () => void
    const gate = new Promise<void>((resolve) => { release = resolve })
    stub(
      [
        { event: 'message.created', data: { user_message: userMessage } },
        { event: 'delta', data: { text: 'Ci' } },
        { event: 'delta', data: { text: 'ao!' } },
        {
          event: 'message.completed',
          data: { assistant_message: assistantMessage, conversation: { ...conversation, title: 'hi' } },
        },
      ],
      undefined,
      gate,
    )
    const onCreated = vi.fn()
    const { result, queryClient } = setup()

    let promise!: Promise<unknown>
    act(() => {
      promise = result.current.send({ input, conversationId: null, onConversationCreated: onCreated })
    })
    await waitFor(() => expect(result.current.streamingText).toBe('Ci'))
    release()
    await act(async () => { await promise })

    expect(onCreated).toHaveBeenCalledWith(conversation)
    expect(cached(queryClient)?.messages.map((m) => m.id)).toEqual([10, 11])
    expect(cached(queryClient)?.title).toBe('hi')
    expect(result.current.streamingText).toBe('')
  })

  it('on an error event removes the user message and rejects so the composer can restore (AC-021)', async () => {
    stub([
      { event: 'message.created', data: { user_message: userMessage } },
      { event: 'error', data: { message: 'Provider down' } },
    ])
    const { result, queryClient } = setup()
    queryClient.setQueryData(aiAssistantKeys.conversation(1), { ...conversation, messages: [] })

    await act(async () => {
      await expect(result.current.send({ input, conversationId: 1 })).rejects.toBeInstanceOf(AiStreamError)
    })

    expect(cached(queryClient)?.messages).toEqual([])
    await waitFor(() => expect(result.current.error?.message).toBe('Provider down'))
  })

  it('rejects with the typed HTTP error and leaves the cache untouched', async () => {
    stub([], new AiStreamHttpError(503, 'Not configured'))
    const { result, queryClient } = setup()
    queryClient.setQueryData(aiAssistantKeys.conversation(1), { ...conversation, messages: [userMessage] })

    await act(async () => {
      await expect(result.current.send({ input, conversationId: 1 })).rejects.toMatchObject({ status: 503 })
    })

    expect(cached(queryClient)?.messages).toHaveLength(1)
  })

  it('treats a stream closed without a final event as an error and invalidates the caches', async () => {
    stub([
      { event: 'message.created', data: { user_message: userMessage } },
      { event: 'delta', data: { text: 'Ci' } },
    ])
    const { result, queryClient } = setup()
    queryClient.setQueryData(aiAssistantKeys.conversation(1), { ...conversation, messages: [] })
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries')

    await act(async () => {
      await expect(result.current.send({ input, conversationId: 1 })).rejects.toMatchObject({
        message: AI_STREAM_INTERRUPTED,
      })
    })

    expect(invalidate).toHaveBeenCalledWith({ queryKey: aiAssistantKeys.conversation(1) })
    expect(invalidate).toHaveBeenCalledWith({ queryKey: aiAssistantKeys.conversations })
  })
})
