const SECOND_MS = 1000
const MINUTE_MS = 60 * SECOND_MS
const HOUR_MS = 60 * MINUTE_MS

/** Localized "5 minutes ago" style label for an ISO timestamp. */
export function formatRelativeTime(iso: string, locale: string, now: number = Date.now()): string {
  const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' })
  const elapsed = now - new Date(iso).getTime()

  if (elapsed < MINUTE_MS) {
    return formatter.format(-Math.max(0, Math.round(elapsed / SECOND_MS)), 'second')
  }
  if (elapsed < HOUR_MS) {
    return formatter.format(-Math.round(elapsed / MINUTE_MS), 'minute')
  }
  return formatter.format(-Math.round(elapsed / HOUR_MS), 'hour')
}
