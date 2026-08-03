/**
 * The single date/time display formatter of the app: every date rendered on
 * screen goes through here, so the per-user preference (Settings → System) is
 * honoured everywhere instead of each screen picking its own Intl options.
 *
 * The active preference is module state, NOT a React context, on purpose: these
 * are plain functions called from AG Grid cell renderers, `valueFormatter`s and
 * other non-React code — the same reason `i18n.language` is read this way.
 * `DateDisplayProvider` keeps it in step with the authenticated user.
 *
 * Patterns are composed by hand rather than through `Intl.DateTimeFormat`
 * because the user picks the pattern explicitly (dd/MM vs MM/dd vs ISO); the UI
 * language must not silently override it.
 */

export const DATE_FORMATS = ['dmy', 'mdy', 'ymd'] as const

export type DateFormat = (typeof DATE_FORMATS)[number]

export const TIME_FORMATS = ['24h', '12h'] as const

export type TimeFormat = (typeof TIME_FORMATS)[number]

/** Mirrors the backend defaults (UserResource): Italian day-first, 24h clock. */
export const DATE_FORMAT_DEFAULT: DateFormat = 'dmy'

export const TIME_FORMAT_DEFAULT: TimeFormat = '24h'

/** A bare calendar date on the wire (`Y-m-d`), with no time part. */
const DATE_ONLY = /^\d{4}-\d{2}-\d{2}$/

/** An instant whose optional time was never set lands on the wire at midnight. */
const MIDNIGHT_INSTANT = /T00:00(:00)?$/

let activeDateFormat: DateFormat = DATE_FORMAT_DEFAULT
let activeTimeFormat: TimeFormat = TIME_FORMAT_DEFAULT

export function isDateFormat(value: unknown): value is DateFormat {
  return DATE_FORMATS.includes(value as DateFormat)
}

export function isTimeFormat(value: unknown): value is TimeFormat {
  return TIME_FORMATS.includes(value as TimeFormat)
}

/** Unknown/absent values fall back to the default rather than throwing. */
export function toDateFormat(value: unknown): DateFormat {
  return isDateFormat(value) ? value : DATE_FORMAT_DEFAULT
}

export function toTimeFormat(value: unknown): TimeFormat {
  return isTimeFormat(value) ? value : TIME_FORMAT_DEFAULT
}

/** Switches the patterns every formatter below renders with, app-wide. */
export function applyDateDisplayPreferences(dateFormat: DateFormat, timeFormat: TimeFormat): void {
  activeDateFormat = dateFormat
  activeTimeFormat = timeFormat
}

export function getDateFormat(): DateFormat {
  return activeDateFormat
}

export function getTimeFormat(): TimeFormat {
  return activeTimeFormat
}

function pad(value: number): string {
  return value < 10 ? `0${value}` : String(value)
}

/**
 * Parses a wire value into a local Date, or null when it carries no usable
 * date. A bare `Y-m-d` is read as UTC midnight by the Date constructor, which
 * renders as the PREVIOUS day west of Greenwich — so it is forced to local
 * midnight instead.
 */
function toDate(value: unknown): Date | null {
  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : value
  }
  if (typeof value !== 'string' || value === '') {
    return null
  }
  const date = new Date(DATE_ONLY.test(value) ? `${value}T00:00:00` : value)

  return Number.isNaN(date.getTime()) ? null : date
}

function formatDatePart(date: Date, dateFormat: DateFormat): string {
  const day = pad(date.getDate())
  const month = pad(date.getMonth() + 1)
  const year = String(date.getFullYear())

  switch (dateFormat) {
    case 'mdy':
      return `${month}/${day}/${year}`
    case 'ymd':
      return `${year}-${month}-${day}`
    default:
      return `${day}/${month}/${year}`
  }
}

function formatTimePart(date: Date, timeFormat: TimeFormat): string {
  const minutes = pad(date.getMinutes())

  if (timeFormat === '12h') {
    const hours = date.getHours()
    return `${hours % 12 || 12}:${minutes} ${hours < 12 ? 'AM' : 'PM'}`
  }

  return `${pad(date.getHours())}:${minutes}`
}

/**
 * A date rendered with an EXPLICIT pattern instead of the active preference,
 * for the settings preview (which shows a pending, not-yet-saved choice).
 */
export function formatDateWith(value: unknown, dateFormat: DateFormat): string {
  const date = toDate(value)

  return date === null ? '' : formatDatePart(date, dateFormat)
}

/** Companion of {@see formatDateWith} for a date followed by its time. */
export function formatDateTimeWith(
  value: unknown,
  dateFormat: DateFormat,
  timeFormat: TimeFormat,
): string {
  const date = toDate(value)

  return date === null ? '' : `${formatDatePart(date, dateFormat)} ${formatTimePart(date, timeFormat)}`
}

/** A date with no time part, blank when the value is missing or unparsable. */
export function formatDate(value: unknown): string {
  return formatDateWith(value, activeDateFormat)
}

/** A date followed by its time, blank when the value is missing or unparsable. */
export function formatDateTime(value: unknown): string {
  return formatDateTimeWith(value, activeDateFormat, activeTimeFormat)
}

/**
 * Same, for a value whose TIME is optional (user directive 2026-07-31): one
 * saved without an hour carries `T00:00`, and printing "00:00" would read as a
 * real midnight appointment — so it renders as a plain date.
 */
export function formatDateTimeOptionalTime(value: unknown): string {
  const timeless =
    typeof value === 'string' && (MIDNIGHT_INSTANT.test(value) || DATE_ONLY.test(value))

  return timeless ? formatDate(value) : formatDateTime(value)
}
