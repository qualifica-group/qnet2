import { AxiosError, type AxiosResponse } from 'axios'
import { describe, expect, it } from 'vitest'
import { classifyAiSendError } from '@/features/ai-assistant/send-error'

function httpError(status: number, data: unknown = {}): AxiosError {
  return new AxiosError('failed', 'ERR_BAD_REQUEST', undefined, undefined, { status, data } as AxiosResponse)
}

describe('classifyAiSendError', () => {
  it.each([
    [503, { data: { reason: 'switched_off' } }, 'switchedOff'],
    [503, { data: { reason: 'not_configured' } }, 'notConfigured'],
    [503, {}, 'notConfigured'],
    [403, {}, 'forbidden'],
    [409, {}, 'busy'],
    [500, {}, 'generic'],
  ])('maps %s %j to %s', (status, body, kind) => {
    expect(classifyAiSendError(httpError(status, body)).kind).toBe(kind)
  })

  it('maps the 422 field messages onto the composer fields', () => {
    const result = classifyAiSendError(httpError(422, { errors: { 'mentions.0.id': ['Bad mention'], other: ['x'] } }))
    expect(result).toEqual({ kind: 'validation', fieldErrors: { mentions: 'Bad mention' } })
  })

  it('falls back to generic for a non-HTTP error', () => {
    expect(classifyAiSendError(new Error('boom')).kind).toBe('generic')
  })
})
