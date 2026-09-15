import { isAxiosError } from 'axios'

/**
 * Whether a save request failed because the request body was too large
 * (413) — the editor itself has no visibility into the save call (it only
 * hands the caller HTML through `onChange`), so callers that submit a rich
 * text field check their own mutation error against this before falling
 * back to a generic message. Not wired into any feature here — see
 * `richText.errors.payloadTooLarge` for the matching i18n key.
 */
export function isPayloadTooLargeError(error: unknown): boolean {
  return isAxiosError(error) && error.response?.status === 413
}
