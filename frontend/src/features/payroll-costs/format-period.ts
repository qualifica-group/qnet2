const PERIOD_PATTERN = /^(\d{4})-(\d{2})$/

/** "2026-10" -> "October 2026" in the UI language; unparsable input is returned as is. */
export function formatPeriod(period: string, language: string): string {
  const match = PERIOD_PATTERN.exec(period)
  if (!match) return period
  const date = new Date(Number(match[1]), Number(match[2]) - 1, 1)
  return new Intl.DateTimeFormat(language, { month: 'long', year: 'numeric' }).format(date)
}
