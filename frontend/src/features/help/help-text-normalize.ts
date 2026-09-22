/**
 * Case- and accent-insensitive normalization for help search (AC-005):
 * "telefonate", "TELEFONATE" and "telefonate" without accents all match the
 * same indexed text. `NFD` decomposes accented characters into a base
 * letter plus a combining diacritic, which the regex then strips.
 */
const DIACRITICS_PATTERN = /[̀-ͯ]/g

export function normalizeHelpSearchText(text: string): string {
  return text.normalize('NFD').replace(DIACRITICS_PATTERN, '').toLowerCase()
}

export const HELP_SEARCH_MIN_LENGTH = 2
