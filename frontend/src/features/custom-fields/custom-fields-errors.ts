import axios from 'axios'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { rawKey, type CustomFieldDescriptor, type CustomFieldsFormShape } from '@/features/custom-fields/types'

/**
 * Builds the list of RHF paths a host form must pass to the existing
 * `applyServerValidationErrors` (`features/auth/form-errors`) so a 422 on
 * `custom_fields.<rawKey>` maps onto the matching `custom_fields.<rawKey>`
 * RHF field — no bespoke mapping needed, the backend error key and the RHF
 * path are identical by construction.
 */
export function customFieldErrorPaths<TFieldValues extends FieldValues & CustomFieldsFormShape>(
  fields: CustomFieldDescriptor[],
): Path<TFieldValues>[] {
  return fields.map((descriptor) => `custom_fields.${rawKey(descriptor.key)}` as Path<TFieldValues>)
}

/** `<prefix>.<key>.rows`, `.rows.N.id` or `.rows.N.<col>`: the nested keys the server emits for a `table` value (spec 0180). */
const TABLE_ERROR_KEY = /^([^.]+)\.rows(\.\d+\.[a-z][a-z0-9_]*)?$/

/**
 * Maps the nested 422 keys of table fields (`<pathPrefix>.<key>.rows[.N.<col>]`)
 * onto the matching RHF paths; `applyServerValidationErrors` only handles
 * exact, pre-listed paths and cannot know the row indexes. `pathPrefix` is
 * `custom_fields` or `attribute_values`. Call it next to that function.
 */
export function applyTableServerErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  pathPrefix: string,
): void {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  const scope = `${pathPrefix}.`
  for (const [key, messages] of Object.entries(errors ?? {})) {
    const message = messages?.[0]
    if (message && key.startsWith(scope) && TABLE_ERROR_KEY.test(key.slice(scope.length))) {
      setError(key as Path<T>, { message })
    }
  }
}
