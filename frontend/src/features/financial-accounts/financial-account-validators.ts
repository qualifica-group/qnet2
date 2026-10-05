/**
 * Formal validators for the financial-accounts module, mirroring the backend
 * rules (spec 0189): IBAN structure + ISO 7064 mod-97 checksum (any country)
 * and the Luhn checksum for card numbers. Pure functions, no dependencies.
 */

const IBAN_STRUCTURE = /^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/
const IBAN_MAX_LENGTH = 34
const IBAN_MOD = 97
const IBAN_EXPECTED_REMAINDER = 1
const LETTER_BASE = 10
const CHAR_CODE_A = 65

const CARD_NUMBER_PATTERN = /^\d{12,19}$/

/** Uppercase, whitespace-free IBAN, the form the backend stores. */
export function normalizeIban(value: string): string {
  return value.replace(/\s+/g, '').toUpperCase()
}

/** True when the value is a structurally valid IBAN with a correct mod-97 checksum. */
export function isValidIban(value: string): boolean {
  const iban = normalizeIban(value)
  if (iban.length > IBAN_MAX_LENGTH || !IBAN_STRUCTURE.test(iban)) {
    return false
  }

  const rearranged = iban.slice(4) + iban.slice(0, 4)
  let remainder = 0
  for (const char of rearranged) {
    const digits = /\d/.test(char)
      ? char
      : String(char.charCodeAt(0) - CHAR_CODE_A + LETTER_BASE)
    for (const digit of digits) {
      remainder = (remainder * 10 + Number(digit)) % IBAN_MOD
    }
  }

  return remainder === IBAN_EXPECTED_REMAINDER
}

/** Digits only: strips the spaces/dashes people type into a card number. */
export function normalizeCardNumber(value: string): string {
  return value.replace(/[\s-]+/g, '')
}

/** True when the value is 12-19 digits (after normalization) passing the Luhn checksum. */
export function isValidCardNumber(value: string): boolean {
  const digits = normalizeCardNumber(value)
  if (!CARD_NUMBER_PATTERN.test(digits)) {
    return false
  }

  let sum = 0
  let double = false
  for (let index = digits.length - 1; index >= 0; index -= 1) {
    let digit = Number(digits[index])
    if (double) {
      digit *= 2
      if (digit > 9) {
        digit -= 9
      }
    }
    sum += digit
    double = !double
  }

  return sum % 10 === 0
}
