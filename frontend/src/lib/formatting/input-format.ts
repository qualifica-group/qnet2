import { normalizeTaxCode } from '@/lib/fiscal/tax-code'
import { normalizeVatNumber } from '@/lib/fiscal/vat-number'

/**
 * Canonical shape a user-typed value is stored in (user directive 2026-07-23):
 * the same number typed `333 12 34 567`, `333-1234567` or `(333) 1234567` must
 * land in the database identically, and the same for names and fiscal codes.
 *
 * Mirrors `backend/app/Support/InputFormat.php` 1:1 — the server stays
 * authoritative and re-applies all of this; formatting here is what lets the
 * field show the stored shape as soon as it loses focus, instead of only after
 * the next refetch. Keep the two twins aligned (same cases in both test files).
 */

/** Contact types whose value is a phone number. */
const PHONE_TYPES = new Set(['phone', 'fax'])
/** Contact types whose value is a mail address (case-insensitive in practice). */
const EMAIL_TYPES = new Set(['email', 'pec'])

/**
 * Digits only, keeping an international prefix as a leading `+`: no country
 * code is ever ASSUMED, so a number typed without one stays without one. A
 * leading `00` is that same prefix written differently, so it collapses onto
 * `+`.
 */
export function formatPhone(value: string): string {
  const kept = value.trim().replace(/[^0-9+]/g, '')
  // A `+` is only a prefix marker in first position; anywhere else it is
  // typing noise (e.g. `333+444`), never part of the number.
  const hasPlus = kept.startsWith('+')
  const digits = kept.replace(/\D/g, '')

  if (!hasPlus && digits.startsWith('00')) {
    return `+${digits.slice(2)}`
  }

  return hasPlus ? `+${digits}` : digits
}

/**
 * Title case over collapsed whitespace: `  mario   ROSSI ` -> `Mario Rossi`.
 * The letter after an apostrophe is uppercased too — the Italian surnames
 * `D'Angelo` / `Dell'Acqua` are the common case, not an edge one.
 */
export function formatPersonName(value: string): string {
  return value
    .trim()
    .replace(/\s+/g, ' ')
    .toLocaleLowerCase()
    .replace(/\p{L}[\p{L}\p{M}'’-]*/gu, capitalizeWord)
}

function capitalizeWord(word: string): string {
  return word.replace(/(^|[-'’])(\p{L})/gu, (_match, boundary: string, letter: string) =>
    boundary + letter.toLocaleUpperCase(),
  )
}

/** Collapsed whitespace only: a company name's own casing is meaningful (`SRL`, `iGuzzini`). */
export function formatPlainText(value: string): string {
  return value.trim().replace(/\s+/g, ' ')
}

/** Uppercase, stripped of separators — the fiscal twin's own normalization. */
export function formatTaxCode(value: string): string {
  return normalizeTaxCode(value)
}

/** Eleven digits, stripped of separators and of the optional `IT` country prefix. */
export function formatVatNumber(value: string): string {
  return normalizeVatNumber(value)
}

/** Seven alphanumerics, uppercase — same shape rule as the fiscal codes. */
export function formatSdiCode(value: string): string {
  return value.trim().toUpperCase().replace(/[^A-Z0-9]/g, '')
}

/**
 * The canonical form of an identity value, dispatched on its FIELD name — the
 * mapping the backend's `InputFormat::identityField()` holds. A field outside
 * the list is returned untouched.
 *
 * A company card's `tax_code` holds the eleven-digit code, whose normalization
 * also drops the optional `IT` prefix — dropping it from a personal code would
 * corrupt it (a surname CAN encode to `IT...`), hence the explicit flag.
 */
export function formatIdentityField(
  field: string,
  value: string,
  isCompany = false,
): string {
  switch (field) {
    case 'first_name':
    case 'last_name':
      return formatPersonName(value)
    case 'company_name':
      return formatPlainText(value)
    case 'tax_code':
      return isCompany ? formatVatNumber(value) : formatTaxCode(value)
    case 'vat_number':
      return formatVatNumber(value)
    case 'sdi_code':
      return formatSdiCode(value)
    default:
      return value
  }
}

/**
 * The canonical form of a contact `value`, dispatched on its channel: phone
 * types collapse to digits, mail types to a trimmed lowercase address, and a
 * website is only trimmed — a URL path IS case-sensitive.
 */
export function formatContactValue(type: string, value: string): string {
  if (PHONE_TYPES.has(type)) {
    return formatPhone(value)
  }

  if (EMAIL_TYPES.has(type)) {
    return value.trim().toLowerCase()
  }

  return value.trim()
}
