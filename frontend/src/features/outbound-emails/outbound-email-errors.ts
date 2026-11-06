import axios from 'axios'
import type { FieldPath, UseFormSetError } from 'react-hook-form'
import type { RecurrenceFormValues } from '@/features/outbound-emails/outbound-email-recurrence-schema'
import type { ComposerFormValues } from '@/features/outbound-emails/outbound-email-schema'

/** The composer's own form fields the `/emails[/{email}]` errors list (data_contract) can target. */
const MAPPED_FIELDS: (keyof ComposerFormValues)[] = ['from_address', 'to', 'cc', 'bcc', 'subject', 'body']

/** Rule fields a `recurrence.*` 422 (spec 0362) can target; a per-item key (`recurrence.weekdays.0`) folds onto its field. */
const RECURRENCE_ERROR_FIELDS = [
  'frequency',
  'interval',
  'weekdays',
  'month_mode',
  'month_day',
  'ordinal',
  'ordinal_weekday',
  'year_month',
  'workdays_only',
  'starts_on',
  'ends_on',
] as const satisfies readonly (keyof RecurrenceFormValues)[]

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

  for (const field of RECURRENCE_ERROR_FIELDS) {
    const message = Object.keys(errors)
      .filter((key) => key === `recurrence.${field}` || key.startsWith(`recurrence.${field}.`))
      .map((key) => errors[key]?.[0])
      .find((entry): entry is string => entry !== undefined)
    if (message) {
      setError(`recurrence.${field}`, { message })
    }
  }

  return true
}

/** Puts client-side schema issues (possibly nested, e.g. `recurrence.ends_on`) on the matching form fields. */
export function applySchemaIssues(
  issues: readonly { path: readonly PropertyKey[]; message: string }[],
  setError: UseFormSetError<ComposerFormValues>,
): void {
  for (const issue of issues) {
    setError(issue.path.join('.') as FieldPath<ComposerFormValues>, { message: issue.message })
  }
}

const HTTP_CONFLICT = 409

/** The server's own sentence for a 409 (e.g. work order no longer in progress, spec 0362), or `null`. */
export function conflictMessage(error: unknown): string | null {
  if (!axios.isAxiosError(error) || error.response?.status !== HTTP_CONFLICT) {
    return null
  }
  const message = (error.response.data as { message?: unknown } | undefined)?.message
  return typeof message === 'string' && message.trim() !== '' ? message : null
}
