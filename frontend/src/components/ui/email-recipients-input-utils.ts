/** A pickable recipient (spec 0175 D-5): registry/referent contacts, work order team members. */
export interface EmailRecipientSuggestion {
  email: string
  label: string
  source?: string
}

/** Separators accepted both while typing (comma) and on a multi-address paste (comma, semicolon, whitespace). */
const TOKEN_SEPARATOR_PATTERN = /[,;\s]+/

/** Deliberately permissive (client-side hint only, D-5 `email:rfc` is the real gate): local@domain.tld shape. */
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export function isValidEmail(candidate: string): boolean {
  return EMAIL_PATTERN.test(candidate)
}

export function splitTokens(raw: string): string[] {
  return raw
    .split(TOKEN_SEPARATOR_PATTERN)
    .map((token) => token.trim())
    .filter((token) => token.length > 0)
}

/** Suggestion pool minus whatever is already committed as a chip. */
export function availableSuggestions(
  suggestions: EmailRecipientSuggestion[],
  value: string[],
): EmailRecipientSuggestion[] {
  const taken = new Set(value)
  return suggestions.filter((suggestion) => !taken.has(suggestion.email))
}
