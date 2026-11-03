import axios from 'axios'
import { apiClient } from '@/api/client'
import type { ApiErrorResponse } from '@/api/types'
import { createSseParser, type SseFrame } from '@/features/ai-assistant/sse-parser'
import type { AiStreamEvent, SendAiMessageInput } from '@/features/ai-assistant/types'

const KNOWN_EVENTS = new Set<AiStreamEvent['event']>([
  'message.created',
  'delta',
  'message.completed',
  'error',
])

/** HTTP failure before the stream opens (401/403/404/422/503), message from the envelope. */
export class AiStreamHttpError extends Error {
  readonly status: number
  readonly fieldErrors: Record<string, string[]>

  constructor(status: number, message: string, fieldErrors: Record<string, string[]> = {}) {
    super(message)
    this.name = 'AiStreamHttpError'
    this.status = status
    this.fieldErrors = fieldErrors
  }
}

/** Neutral message of the error raised when the stream closes without a final event; the UI shows its own translation. */
export const AI_STREAM_INTERRUPTED = 'ai_stream_interrupted'

/** The provider failed after the stream opened (`error` SSE event, D-13). */
export class AiStreamError extends Error {
  constructor(message: string) {
    super(message)
    this.name = 'AiStreamError'
  }
}

/** Builds the multipart body of the send endpoint (spec 0347 data_contract). */
export function buildAiMessageFormData(input: SendAiMessageInput): FormData {
  const form = new FormData()
  form.append('content', input.content)
  input.mentions.forEach((mention, index) => {
    form.append(`mentions[${index}][module]`, mention.module)
    form.append(`mentions[${index}][id]`, String(mention.id))
  })
  input.files.forEach((file) => form.append('files[]', file))
  if (input.pageGuideKey) {
    form.append('page_guide_key', input.pageGuideKey)
  }
  if (input.pageGuideText) {
    form.append('page_guide_text', input.pageGuideText)
  }
  form.append('locale', input.locale)
  return form
}

async function readText(body: ReadableStream<Uint8Array>): Promise<string> {
  const reader = body.getReader()
  const decoder = new TextDecoder()
  let text = ''
  for (;;) {
    const { done, value } = await reader.read()
    if (done) {
      return text + decoder.decode()
    }
    text += decoder.decode(value, { stream: true })
  }
}

/**
 * With `responseType: 'stream'` an error response body is a stream as well,
 * so the envelope has to be read from it before building the typed error.
 */
async function toHttpError(error: unknown): Promise<unknown> {
  if (!axios.isAxiosError(error) || !error.response) {
    return error
  }
  const { status, data } = error.response
  let envelope: Partial<ApiErrorResponse> = {}
  try {
    const raw = data instanceof ReadableStream ? await readText(data) : data
    envelope = (typeof raw === 'string' ? JSON.parse(raw) : raw) as Partial<ApiErrorResponse>
  } catch {
    // Non-JSON body: fall back to the generic axios message.
  }
  return new AiStreamHttpError(status, envelope?.message ?? error.message, envelope?.errors)
}

function toStreamEvent(frame: SseFrame): AiStreamEvent | null {
  if (!KNOWN_EVENTS.has(frame.event as AiStreamEvent['event'])) {
    return null
  }
  try {
    return { event: frame.event, data: JSON.parse(frame.data) } as AiStreamEvent
  } catch {
    return null
  }
}

/**
 * Posts a message and yields the SSE events as they arrive. HTTP errors
 * before the stream opens reject as `AiStreamHttpError` on the first `next()`.
 */
export async function* streamAiMessage(
  conversationId: number,
  input: SendAiMessageInput,
  signal?: AbortSignal,
): AsyncGenerator<AiStreamEvent> {
  let body: ReadableStream<Uint8Array>
  try {
    const response = await apiClient.post<ReadableStream<Uint8Array>>(
      `/ai-assistant/conversations/${conversationId}/messages`,
      buildAiMessageFormData(input),
      {
        adapter: 'fetch',
        responseType: 'stream',
        headers: { Accept: 'text/event-stream' },
        signal,
      },
    )
    body = response.data
  } catch (error) {
    throw await toHttpError(error)
  }

  const reader = body.getReader()
  const decoder = new TextDecoder()
  const parser = createSseParser()

  try {
    for (;;) {
      const { done, value } = await reader.read()
      const frames = done
        ? [...parser.push(decoder.decode()), ...parser.end()]
        : parser.push(decoder.decode(value, { stream: true }))
      for (const frame of frames) {
        const event = toStreamEvent(frame)
        if (event) {
          yield event
        }
      }
      if (done) {
        return
      }
    }
  } finally {
    await reader.cancel().catch(() => undefined)
  }
}
