/** Share in percent with one decimal, in the active UI language (the server sends 0..100). */
export function formatShare(value: number, locale: string): string {
  const formatted = new Intl.NumberFormat(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(value)
  return `${formatted}%`
}

const MINUTES_PER_HOUR = 60

/** Minutes as hours and minutes ("4:30"): the way a timesheet reads, never a decimal fraction of an hour. */
export function formatHours(minutes: number): string {
  const hours = Math.floor(minutes / MINUTES_PER_HOUR)
  return `${hours}:${String(minutes % MINUTES_PER_HOUR).padStart(2, '0')}`
}
