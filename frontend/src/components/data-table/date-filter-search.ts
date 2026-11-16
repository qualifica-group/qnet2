import { getDateFormat, type DateFormat } from '@/lib/formatting/date-display'

/**
 * Server search inside the checklist of a DATE column (task 427789).
 *
 * The checklist shows dates in the user's pattern (`15/01/2026`), while the
 * values endpoint matches the search as a substring of the stored value
 * (`2026-01-15`). A search typed in the user's pattern is rewritten into the
 * matching piece of the stored value: `01/2026` -> `2026-01` (the whole month),
 * `15/01` -> `-01-15`, `15/01/2026` -> `2026-01-15`. Anything else (a bare
 * year, a time, free text) goes to the server unchanged.
 */

type DatePart = 'day' | 'month' | 'year'

const PART_ORDER: Record<DateFormat, readonly DatePart[]> = {
  dmy: ['day', 'month', 'year'],
  mdy: ['month', 'day', 'year'],
  ymd: ['year', 'month', 'day'],
}

/** What a user may type between the parts of a date. */
const PART_SEPARATOR = /[/.\-\s]+/

const DAY_OR_MONTH = /^\d{1,2}$/

const YEAR = /^\d{4}$/

function pad(value: string): string {
  return value.padStart(2, '0')
}

/** The parts the user typed, labelled by the active pattern; null when they do not fit it. */
function labelParts(parts: string[], dateFormat: DateFormat): Partial<Record<DatePart, string>> | null {
  const order = PART_ORDER[dateFormat]
  // Two parts are the leading pair of the pattern, or the trailing pair when the
  // second one is a year (`01/2026` in day-first means month and year).
  const offset = parts.length === 2 && YEAR.test(parts[1]) && order[2] === 'year' ? 1 : 0
  const labelled: Partial<Record<DatePart, string>> = {}

  for (const [index, part] of parts.entries()) {
    const label = order[index + offset]
    if (!(label === 'year' ? YEAR : DAY_OR_MONTH).test(part)) {
      return null
    }
    labelled[label] = part
  }

  return labelled
}

/** The stored-value piece matching the labelled parts; null when they are not contiguous in it. */
function toStoredFragment({ day, month, year }: Partial<Record<DatePart, string>>): string | null {
  if (year && month && day) {
    return `${year}-${pad(month)}-${pad(day)}`
  }
  if (year && month) {
    return `${year}-${pad(month)}`
  }
  if (month && day) {
    return `-${pad(month)}-${pad(day)}`
  }

  return null
}

export function toDateServerSearch(search: string, dateFormat: DateFormat = getDateFormat()): string {
  const parts = search.trim().split(PART_SEPARATOR).filter((part) => part !== '')
  if (parts.length < 2 || parts.length > 3) {
    return search
  }
  const labelled = labelParts(parts, dateFormat)

  return (labelled && toStoredFragment(labelled)) ?? search
}
