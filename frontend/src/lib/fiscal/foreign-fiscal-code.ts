/**
 * A foreign VAT number or tax identifier, recognized by the two-letter country
 * prefix it is written with (`DE123456789`, `FR12345678901`, `CHE123456789`).
 * Only the shape is checked: prefix (never `IT`, whose codes keep their own
 * algorithms) followed by 2 to 16 alphanumerics.
 *
 * Mirrors `backend/app/Support/Fiscal/ForeignFiscalCode.php` 1:1. The server
 * stays authoritative; this is the client-side echo of the same rule.
 */

const FOREIGN_CODE_PATTERN = /^(?!IT)[A-Z]{2}[A-Z0-9]{2,16}$/

/**
 * Six leading letters are the surname/name triples of an Italian personal
 * code: such a value is a mistyped Italian code, never a foreign one.
 */
const ITALIAN_PERSONAL_SHAPE = /^[A-Z]{6}/

export function isValidForeignFiscalCode(value: string): boolean {
  const code = value.trim().toUpperCase().replace(/[^A-Z0-9]/g, '')

  return FOREIGN_CODE_PATTERN.test(code) && !ITALIAN_PERSONAL_SHAPE.test(code)
}
