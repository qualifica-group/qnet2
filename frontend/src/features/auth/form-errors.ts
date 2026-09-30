import axios from 'axios'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { applyTableServerErrors } from '@/features/custom-fields/custom-fields-errors'

/** Roots under which a `table` value (spec 0180) can travel: nested `rows.N.<col>` keys are mapped for them. */
const TABLE_VALUE_PREFIXES = ['custom_fields', 'attribute_values']

/**
 * Maps a backend 422 validation response onto react-hook-form fields.
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

  return true
}
