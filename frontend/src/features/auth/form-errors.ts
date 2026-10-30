import axios from 'axios'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { applyTableServerErrors } from '@/features/custom-fields/custom-fields-errors'

/** Roots under which a `table` value (spec 0180) can travel: nested `rows.N.<col>` keys are mapped for them. */
const TABLE_VALUE_PREFIXES = ['custom_fields', 'attribute_values']

/** One toast per refused save: a resubmit (or a second mapper on the same 422) replaces it instead of stacking. */
const FORM_ERRORS_TOAST_ID = 'form-errors'

/** Reasons listed in the toast before collapsing the rest into a "...and N more" line. */
const MAX_TOASTED_MESSAGES = 4

/** Longer than sonner's default: the operator has to read the reasons, not glimpse them. */
const FORM_ERRORS_TOAST_DURATION_MS = 10_000

/**
 * User directive 2026-10-30: a refused save must be EXPLICIT, never only an
 * inline message on a field the operator may not be looking at (a long form
 * submitted from its bottom bar, a field in another section, a 422 key no
 * input owns). Raises one foreground toast listing the distinct reasons.
 */
export function notifyFormErrors(messages: readonly string[]): void {
  const distinct = [...new Set(messages.map((message) => message.trim()).filter((message) => message !== ''))]
  if (distinct.length === 0) {
    return
  }

  const hidden = distinct.length - MAX_TOASTED_MESSAGES
  const lines = distinct.slice(0, MAX_TOASTED_MESSAGES)
  if (hidden > 0) {
    lines.push(i18n.t('common.formErrors.more', { count: hidden }))
  }

  toast.error(i18n.t('common.formErrors.title'), {
    id: FORM_ERRORS_TOAST_ID,
    description: lines.join('\n'),
    duration: FORM_ERRORS_TOAST_DURATION_MS,
    classNames: { description: 'whitespace-pre-line' },
  })
}

/**
 * Maps a backend 422 validation response onto react-hook-form fields, and
 * surfaces every reason in a foreground toast (`notifyFormErrors`).
 * Returns true when the error was a handled 422, so callers can fall back to a
 * generic message for any other failure.
 */
export function applyServerValidationErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  fields: Path<T>[],
): boolean {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return false
  }

  for (const prefix of TABLE_VALUE_PREFIXES) {
    applyTableServerErrors(error, setError, prefix)
  }

  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  for (const field of fields) {
    const message = errors?.[field as string]?.[0]
    if (message) {
      setError(field, { message })
    }
  }

  const fieldMessages = Object.values(errors ?? {}).flatMap((messages) => messages?.[0] ?? [])
  const summary = error.response.data?.message
  notifyFormErrors(fieldMessages.length > 0 || typeof summary !== 'string' ? fieldMessages : [summary])

  return true
}
