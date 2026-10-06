import axios from 'axios'
import type { UseFormSetError } from 'react-hook-form'
import type { ComposerFormValues } from '@/features/outbound-emails/outbound-email-schema'

/** The composer's own form fields the `/emails[/{email}]` errors list (data_contract) can target. */
const MAPPED_FIELDS: (keyof ComposerFormValues)[] = ['to', 'cc', 'bcc', 'subject', 'body']

/**
 * Maps a 422 onto the composer form (AC-020). A whole-array error (`to`,
 * e.g. more than 50 recipients) lands on the field directly; a per-item error
 * (`to.0`, an address that slipped past the client-side hint) is folded onto
 * the same array field instead of being silently dropped — `EmailRecipientsInput`
 * has no per-chip error slot.
 */
export function applyComposerValidationErrors(
  error: unknown,
  setError: UseFormSetError<ComposerFormValues>,
): boolean {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return false
  }
  const errors = (error.response.data?.errors as Record<string, string[]> | undefined) ?? {}

  for (const field of MAPPED_FIELDS) {
    const direct = errors[field]?.[0]
    const nested = Object.keys(errors)
      .filter((key) => key.startsWith(`${field}.`))
      .map((key) => errors[key]?.[0])
      .find((message): message is string => message !== undefined)
    const message = direct ?? nested
    if (message) {
      setError(field, { message })
    }
  }

  return true
}
