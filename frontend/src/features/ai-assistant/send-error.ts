import axios from 'axios'
import { AiStreamHttpError } from '@/features/ai-assistant/ai-sse-stream'

export type AiSendErrorKind = 'notConfigured' | 'forbidden' | 'validation' | 'generic'

export type AiComposerField = 'content' | 'files' | 'mentions'

export interface AiSendFailure {
  kind: AiSendErrorKind
  /** First server message per composer field (422 only). */
  fieldErrors: Partial<Record<AiComposerField, string>>
}

const HTTP_NOT_CONFIGURED = 503
const HTTP_FORBIDDEN = 403
const HTTP_VALIDATION = 422

/** The user pressed stop: not a failure. */
export function isAiSendCancelled(error: unknown): boolean {
  return axios.isCancel(error) || (error instanceof DOMException && error.name === 'AbortError')
}

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
 * Maps a failed send to a translatable kind (AC-021). Server text is never
 * shown for stream errors (it is English and provider-facing); only the 422
 * field messages, which come from Laravel validation, are surfaced on fields.
 */
export function classifyAiSendError(error: unknown): AiSendFailure {
  if (error instanceof AiStreamHttpError) {
    if (error.status === HTTP_NOT_CONFIGURED) {
      return { kind: 'notConfigured', fieldErrors: {} }
    }
    if (error.status === HTTP_FORBIDDEN) {
      return { kind: 'forbidden', fieldErrors: {} }
    }
    if (error.status === HTTP_VALIDATION) {
      return { kind: 'validation', fieldErrors: mapFieldErrors(error.fieldErrors) }
    }
  }
  return { kind: 'generic', fieldErrors: {} }
}
