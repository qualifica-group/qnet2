import type { FieldErrors } from 'react-hook-form'

/**
 * The first client-side message among `errors`, nested ones included (an
 * Attribute is `attribute_values.<code>`, a line `offer_lines.<n>.<field>`).
 * An in-place detail toasts it when the refused field is not the open editor's
 * own, so its message would otherwise be on no screen at all.
 */
export function firstErrorMessage(errors: FieldErrors): string | null {
  for (const [key, error] of Object.entries(errors)) {
    // `ref` is the field's DOM element, not a nested error.
    if (key === 'ref' || !error || typeof error !== 'object') {
      continue
    }
    if (typeof error.message === 'string' && error.message !== '') {
      return error.message
    }
    const nested = firstErrorMessage(error as FieldErrors)
    if (nested) {
      return nested
    }
  }
  return null
}
