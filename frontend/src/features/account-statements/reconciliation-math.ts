/** Signed amount with at most two decimals; comma or dot as separator. */
const SIGNED_AMOUNT_PATTERN = /^-?\d+([.,]\d{1,2})?$/

/** Integer cents of a signed decimal string, or null when it is not a valid amount. Never floats. */
export function parseCents(value: string): number | null {
  const trimmed = value.trim()
  if (!SIGNED_AMOUNT_PATTERN.test(trimmed)) {
    return null
  }
  const negative = trimmed.startsWith('-')
  const [whole, fraction = ''] = trimmed.replace('-', '').replace(',', '.').split('.')
  const cents = Number(whole) * 100 + Number(fraction.padEnd(2, '0'))
  return negative ? -cents : cents
}

/** "-10.00" from integer cents. */
export function centsToDecimal(cents: number): string {
  const absolute = Math.abs(cents)
  return `${cents < 0 ? '-' : ''}${Math.floor(absolute / 100)}.${String(absolute % 100).padStart(2, '0')}`
}

/** Bank balance - ticked balance as a decimal string, or null while the bank balance is not a valid amount. */
export function reconciliationDifference(bankBalance: string, reconciledBalance: string): string | null {
  const bank = parseCents(bankBalance)
  const reconciled = parseCents(reconciledBalance)
  return bank === null || reconciled === null ? null : centsToDecimal(bank - reconciled)
}

/** Value sent to the API: dot separator, no spaces. */
export function normalizeBalance(value: string): string {
  return value.trim().replace(',', '.')
}
