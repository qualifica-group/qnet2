/** Sums of amounts with two decimals: rounded to the cent so float noise never reaches the screen. */
export function roundMoney(value: number): number {
  return Math.round(value * 100) / 100
}

export function sumMoney(values: readonly number[]): number {
  return roundMoney(values.reduce((total, value) => total + value, 0))
}

/** Change of `current` on `previous` in percent; null when there is no base to compare with. */
export function variationPercent(current: number, previous: number): number | null {
  if (previous === 0) {
    return null
  }
  return ((current - previous) / Math.abs(previous)) * 100
}

/** Signed percentage with one decimal in the active UI language ("+12.5%"). */
export function formatVariation(value: number, locale: string): string {
  const formatted = new Intl.NumberFormat(locale, {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
    signDisplay: 'exceptZero',
  }).format(value)
  return `${formatted}%`
}
