/**
 * The `code` slice of a create payload for a form prefilled from a
 * `GET /<resource>/next-code` suggestion. The suggestion is only a preview:
 * another user may save a record with that very code while this form is open.
 * So a blank code, or one still equal to the suggestion, is omitted and the
 * server assigns the next free code at save time (inside its locked
 * transaction). Only a code the user actually typed is sent as manual.
 */
export function submittedCode(code: string, suggestedCode: string | undefined): { code?: string } {
  const trimmed = code.trim()
  if (trimmed === '' || trimmed === suggestedCode?.trim()) {
    return {}
  }
  return { code: trimmed }
}
