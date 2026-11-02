import { useCallback, useState } from 'react'
import { notifyFormErrors } from '@/features/auth/form-errors'

/**
 * The `[submitError, setSubmitError]` pair of a full-page form: the message
 * keeps feeding the form header's banner, and every non-null one is ALSO
 * raised as a foreground toast (`notifyFormErrors`), because the header sits
 * out of view when the operator saves from the bottom actions bar.
 * The setter is stable, like `useState`'s, so it can sit in dependency arrays.
 * Pass `{ notify: false }` for a banner derived from a 422 that
 * `applyServerValidationErrors` has already toasted in full: a second toast
 * would replace that list with a subset of it.
 */
export function useFormSubmitError() {
  const [submitError, setSubmitErrorState] = useState<string | null>(null)

  const setSubmitError = useCallback((message: string | null, options?: { notify?: boolean }) => {
    setSubmitErrorState(message)
    if (message !== null && options?.notify !== false) {
      notifyFormErrors([message])
    }
  }, [])

  return [submitError, setSubmitError] as const
}
