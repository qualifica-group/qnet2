/**
 * Period navigation helpers (spec 0122 AC-032), adapted from q-net's
 * `work-activities-period-navigation.ts`. Pure date math — no React, no
 * i18n resource lookup (the `locale` string is passed in by the caller, same
 * convention as `features/stats/format-trend-label.ts`: `i18n.language`
 * directly, since these are plain functions usable outside components).
 */

export type TimeEntriesPeriodUnit = 'day' | 'week' | 'month' | 'year'

const PERIOD_UNITS: readonly TimeEntriesPeriodUnit[] = ['day', 'week', 'month', 'year']

/** Narrows an arbitrary string (e.g. a stored/query value) to a valid period unit. */
export function isTimeEntriesPeriodUnit(value: string | undefined): value is TimeEntriesPeriodUnit {
  return PERIOD_UNITS.includes(value as TimeEntriesPeriodUnit)
}

function pad(value: number): string {
  return String(value).padStart(2, '0')
}

/** Formats a local `Date` as `Y-m-d`, the wire format every date filter uses. */
export function toDateString(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

/** Parses a `Y-m-d`-prefixed string into a local `Date`, or `null` when absent/unparsable. */
export function parseDateString(value: string | undefined | null): Date | null {
  if (!value) {
    return null
  }
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value)
  if (!match) {
    return null
  }
  const [, year, month, day] = match
  return new Date(Number(year), Number(month) - 1, Number(day))
}

/** `Y-m-d` of today, used to tell the current day's `DaySummary` apart (AC-035). */
export function getTodayDateKey(): string {
  return toDateString(new Date())
}

/**
 * Resolves the `[from, to]` range of a period unit anchored on a given date.
 * `week` is ISO (Monday to Sunday, D-4/data_contract), matching the backend's
 * own week boundary so the client-computed range never disagrees with it.
 */
export function getPeriodRange(
  unit: TimeEntriesPeriodUnit,
  anchor: Date,
): { from: string; to: string } {
  const day = new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate())

  if (unit === 'day') {
    return { from: toDateString(day), to: toDateString(day) }
  }

  if (unit === 'week') {
    const dayOfWeek = day.getDay()
    const offsetToMonday = dayOfWeek === 0 ? -6 : 1 - dayOfWeek
    const monday = new Date(day)
    monday.setDate(day.getDate() + offsetToMonday)
    const sunday = new Date(monday)
    sunday.setDate(monday.getDate() + 6)
    return { from: toDateString(monday), to: toDateString(sunday) }
  }

  if (unit === 'month') {
    const first = new Date(day.getFullYear(), day.getMonth(), 1)
    const last = new Date(day.getFullYear(), day.getMonth() + 1, 0)
    return { from: toDateString(first), to: toDateString(last) }
  }

  const first = new Date(day.getFullYear(), 0, 1)
  const last = new Date(day.getFullYear(), 11, 31)
  return { from: toDateString(first), to: toDateString(last) }
}

/** Moves the anchor `direction` steps of `unit` (negative = previous, positive = next). */
export function shiftAnchor(unit: TimeEntriesPeriodUnit, anchor: Date, direction: number): Date {
  const day = new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate())

  if (unit === 'day') {
    day.setDate(day.getDate() + direction)
  } else if (unit === 'week') {
    day.setDate(day.getDate() + 7 * direction)
  } else if (unit === 'month') {
    day.setMonth(day.getMonth() + direction)
  } else {
    day.setFullYear(day.getFullYear() + direction)
  }

  return day
}

/** Human-readable label of the period a unit+anchor resolves to, in the app's active language. */
export function formatPeriodLabel(unit: TimeEntriesPeriodUnit, anchor: Date, locale: string): string {
  if (unit === 'day') {
    return new Intl.DateTimeFormat(locale, { dateStyle: 'long' }).format(anchor)
  }

  if (unit === 'week') {
    const range = getPeriodRange('week', anchor)
    const fromDate = parseDateString(range.from) ?? anchor
    const toDate = parseDateString(range.to) ?? anchor
    const sameMonth =
      fromDate.getMonth() === toDate.getMonth() && fromDate.getFullYear() === toDate.getFullYear()
    const fullFormat = new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short', year: 'numeric' })

    if (sameMonth) {
      const dayOnlyFormat = new Intl.DateTimeFormat(locale, { day: 'numeric' })
      return `${dayOnlyFormat.format(fromDate)} – ${fullFormat.format(toDate)}`
    }

    const shortFormat = new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short' })
    return `${shortFormat.format(fromDate)} – ${fullFormat.format(toDate)}`
  }

  if (unit === 'month') {
    const label = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(anchor)
    return label.charAt(0).toUpperCase() + label.slice(1)
  }

  return String(anchor.getFullYear())
}

/** Whether `anchor`'s period (of `unit`) is the same one `new Date()` currently falls in. */
export function isSamePeriodAsToday(unit: TimeEntriesPeriodUnit, anchor: Date): boolean {
  const todayRange = getPeriodRange(unit, new Date())
  const anchorRange = getPeriodRange(unit, anchor)
  return todayRange.from === anchorRange.from && todayRange.to === anchorRange.to
}
