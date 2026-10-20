/**
 * Month arithmetic of the absence calendar (spec 0279) on `YYYY-MM` strings.
 * Dates are calendar days, never instants: everything goes through UTC so the
 * browser timezone cannot shift a day.
 */

const MONTH_PATTERN = /^(\d{4})-(\d{2})$/

function parts(month: string): [number, number] {
  const match = MONTH_PATTERN.exec(month)
  if (!match) throw new Error(`Invalid month: ${month}`)
  return [Number(match[1]), Number(match[2])]
}

function pad(value: number): string {
  return String(value).padStart(2, '0')
}

/** `YYYY-MM` of the local day of `date`. */
export function monthOf(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`
}

/** `YYYY-MM-DD` of the local day of `date`. */
export function dayOf(date: Date): string {
  return `${monthOf(date)}-${pad(date.getDate())}`
}

export function shiftMonth(month: string, delta: number): string {
  const [year, monthNumber] = parts(month)
  const shifted = new Date(Date.UTC(year, monthNumber - 1 + delta, 1))
  return `${shifted.getUTCFullYear()}-${pad(shifted.getUTCMonth() + 1)}`
}

/** First and last day of the month, the calendar request period. */
export function monthRange(month: string): { from: string; to: string } {
  const [year, monthNumber] = parts(month)
  const lastDay = new Date(Date.UTC(year, monthNumber, 0)).getUTCDate()
  return { from: `${month}-01`, to: `${month}-${pad(lastDay)}` }
}

/** "dicembre 2026" / "December 2026". */
export function formatMonthLabel(month: string, locale: string): string {
  const [year, monthNumber] = parts(month)
  return new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(
    new Date(Date.UTC(year, monthNumber - 1, 1)),
  )
}

/** Day of month and short weekday of a `YYYY-MM-DD` date, for the column headers. */
export function formatDayHeader(date: string, locale: string): { day: number; weekday: string } {
  const [year, month, day] = date.split('-').map(Number)
  const weekday = new Intl.DateTimeFormat(locale, { weekday: 'narrow', timeZone: 'UTC' }).format(
    new Date(Date.UTC(year, month - 1, day)),
  )
  return { day, weekday }
}
