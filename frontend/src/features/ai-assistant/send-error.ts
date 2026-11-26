import axios from 'axios'
import type { ApiErrorResponse } from '@/api/types'

export type AiSendErrorKind = 'notConfigured' | 'switchedOff' | 'forbidden' | 'busy' | 'validation' | 'generic'

export type AiComposerField = 'content' | 'files' | 'mentions'

export interface AiSendFailure {
  kind: AiSendErrorKind
  /** First server message per composer field (422 only). */
  fieldErrors: Partial<Record<AiComposerField, string>>
}

const HTTP_NOT_CONFIGURED = 503
const HTTP_FORBIDDEN = 403
const HTTP_CONFLICT = 409
const HTTP_VALIDATION = 422
const REASON_SWITCHED_OFF = 'switched_off'

function mapFieldErrors(errors: Record<string, string[]>): AiSendFailure['fieldErrors'] {
  const mapped: AiSendFailure['fieldErrors'] = {}
  for (const [key, messages] of Object.entries(errors)) {
    const field = key.split('.')[0]
    if ((field === 'content' || field === 'files' || field === 'mentions') && messages[0]) {
      mapped[field] ??= messages[0]
    }
  }
  return mapped
}

/**
 * Maps a failed send (the POST that saves the message and queues the turn) to a
 * translatable kind. Server text is never shown: it is English; only the 422
 * field messages, which come from Laravel validation, are surfaced on fields.
 */
export function classifyAiSendError(error: unknown): AiSendFailure {
  if (!axios.isAxiosError<Partial<ApiErrorResponse> & { data?: { reason?: string } }>(error) || !error.response) {
    return { kind: 'generic', fieldErrors: {} }
  }
  const { status, data } = error.response
  if (status === HTTP_NOT_CONFIGURED) {
    return { kind: data?.data?.reason === REASON_SWITCHED_OFF ? 'switchedOff' : 'notConfigured', fieldErrors: {} }
  }
  if (status === HTTP_FORBIDDEN) {
    return { kind: 'forbidden', fieldErrors: {} }
  }
  if (status === HTTP_CONFLICT) {
    return { kind: 'busy', fieldErrors: {} }
  }
  if (status === HTTP_VALIDATION) {
    return { kind: 'validation', fieldErrors: mapFieldErrors(data?.errors ?? {}) }
  }
  return { kind: 'generic', fieldErrors: {} }
}
