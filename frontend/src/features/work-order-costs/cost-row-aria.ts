import type { FieldError } from 'react-hook-form'

/** The accessible error triad: `aria-invalid` + `aria-describedby` (the matching `role="alert"` is `CostRowError`). */
export function errorAria(errorId: string, error?: FieldError) {
  return { 'aria-invalid': !!error, 'aria-describedby': error ? errorId : undefined }
}
