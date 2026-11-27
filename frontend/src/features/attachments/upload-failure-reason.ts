import { isAxiosError } from 'axios'
import type { TFunction } from 'i18next'

/**
 * Why the server refused one uploaded file, as a sentence for the user, or
 * null when the response carries no reason (network error, 5xx).
 *
 * A 413 comes from the web server or PHP before Laravel validates anything
 * (the body exceeded `post_max_size`/`client_max_body_size`), so it has no
 * message of its own; a 422 carries the localized validation message of the
 * `file` field (size over `attachments.max_size`, type not allowed).
 */
export function uploadFailureReason(error: unknown, t: TFunction): string | null {
  if (!isAxiosError(error)) {
    return null
  }

  const status = error.response?.status
  if (status === 413) {
    return t('attachments.errors.reasons.tooLarge')
  }
  if (status !== 422) {
    return null
  }

  const data = error.response?.data as { message?: unknown; errors?: Record<string, string[]> } | undefined
  const fieldMessage = data?.errors?.file?.[0] ?? Object.values(data?.errors ?? {})[0]?.[0]
  if (fieldMessage) {
    return fieldMessage
  }

  return typeof data?.message === 'string' ? data.message : null
}
