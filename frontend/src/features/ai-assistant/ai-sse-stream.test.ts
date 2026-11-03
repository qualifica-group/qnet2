import { AxiosError, type AxiosResponse } from 'axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/api/client', () => ({ apiClient: { post: vi.fn() } }))

import { apiClient } from '@/api/client'
import {
  AiStreamHttpError,
  buildAiMessageFormData,
  streamAiMessage,
} from '@/features/ai-assistant/ai-sse-stream'
import type { AiStreamEvent, SendAiMessageInput } from '@/features/ai-assistant/types'

const encoder = new TextEncoder()

export function streamOf(chunks: string[]): ReadableStream<Uint8Array> {
  return new ReadableStream({
    start(controller) {
      chunks.forEach((chunk) => controller.enqueue(encoder.encode(chunk)))
      controller.close()
    },
  })
}

const input: SendAiMessageInput = {
  content: 'hi',
  mentions: [{ module: 'companies', id: 5, label: 'ACME' }],
  files: [new File(['x'], 'a.pdf', { type: 'application/pdf' })],
  pageGuideKey: 'general',
  pageGuideText: 'guide',
  locale: 'it',
}

async function collect(gen: AsyncGenerator<AiStreamEvent>): Promise<AiStreamEvent[]> {
  const out: AiStreamEvent[] = []
  for await (const event of gen) {
    out.push(event)
  }
  return out
}

describe('buildAiMessageFormData', () => {
  it('uses the multipart field names of the contract', () => {
    const form = buildAiMessageFormData(input)
    expect(form.get('content')).toBe('hi')
    expect(form.get('mentions[0][module]')).toBe('companies')
    expect(form.get('mentions[0][id]')).toBe('5')
    expect(form.getAll('files[]')).toHaveLength(1)
    expect(form.get('page_guide_key')).toBe('general')
    expect(form.get('page_guide_text')).toBe('guide')
    expect(form.get('locale')).toBe('it')
  })
})

describe('streamAiMessage', () => {
  beforeEach(() => vi.clearAllMocks())

  it('yields typed events from a stream split mid-event', async () => {
    const sse =
      'event: delta\ndata: {"text":"Ciao"}\n\nevent: delta\ndata: {"text":"!"}\n\n'
    vi.mocked(apiClient.post).mockResolvedValueOnce({
      data: streamOf([sse.slice(0, 20), sse.slice(20, 45), sse.slice(45)]),
    })

    const events = await collect(streamAiMessage(7, input))

    expect(events).toEqual([
      { event: 'delta', data: { text: 'Ciao' } },
      { event: 'delta', data: { text: '!' } },
    ])
    expect(apiClient.post).toHaveBeenCalledWith(
      '/ai-assistant/conversations/7/messages',
      expect.any(FormData),
      expect.objectContaining({ adapter: 'fetch', responseType: 'stream' }),
    )
  })

  it('rejects with a typed error carrying message and field errors on 422', async () => {
    const body = JSON.stringify({ message: 'Invalid', errors: { content: ['required'] } })
    const error = new AxiosError('Request failed')
    error.response = { status: 422, data: streamOf([body]) } as AxiosResponse
    vi.mocked(apiClient.post).mockRejectedValueOnce(error)

    const failure = await collect(streamAiMessage(7, input)).catch((e: unknown) => e)

    expect(failure).toBeInstanceOf(AiStreamHttpError)
    expect(failure).toMatchObject({
      status: 422,
      message: 'Invalid',
      fieldErrors: { content: ['required'] },
    })
  })
})
