const MIN_DECIMALS = 2

/** A dollar amount from the ledger's decimal string: trailing zeros dropped, at least two decimals, no rounding. */
export function formatUsd(value: string): string {
  const [whole, fraction = ''] = value.split('.')
  const trimmed = fraction.replace(/0+$/, '').padEnd(MIN_DECIMALS, '0')
  return `$${Number(whole).toLocaleString('en-US')}.${trimmed}`
}

/** Chart axes and tooltips: the same amount from a number (display only, never summed). */
export function formatUsdNumber(value: number): string {
  return `$${value.toLocaleString('en-US', { minimumFractionDigits: MIN_DECIMALS, maximumFractionDigits: 6 })}`
}

export function formatCount(value: number): string {
  return value.toLocaleString('en-US')
}
