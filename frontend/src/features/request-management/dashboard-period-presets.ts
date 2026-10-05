import { toLocalIsoDate } from '@/features/request-management/request-report-schema'

/** One-click periods of the statistics toolbar (spec 0192 D-2), in display order. */
export const DASHBOARD_PERIOD_PRESETS = ['today', 'yesterday', 'last7Days', 'thisMonth', 'lastMonth', 'all'] as const

export type DashboardPeriodPreset = (typeof DASHBOARD_PERIOD_PRESETS)[number]

/** The two date fields of the applied filters: `YYYY-MM-DD`, or `''` for an open bound (spec 0169). */
export interface DashboardDateRange {
  date_from: string
  date_to: string
}

/** "Ultimi 7 giorni" counts today as the seventh day. */
const LAST_DAYS_SPAN = 7
const MS_PER_DAY = 86_400_000

function shiftDays(date: Date, days: number): Date {
  const shifted = new Date(date)
  shifted.setDate(shifted.getDate() + days)

  return shifted
}

/** Parses `YYYY-MM-DD` as a LOCAL calendar day, the inverse of `toLocalIsoDate`. */
function parseLocalIsoDate(value: string): Date {
  const [year, month, day] = value.split('-').map(Number)

  return new Date(year, month - 1, day)
}

/** Whole calendar days between two local dates, immune to a DST change in between. */
function daysBetween(from: Date, to: Date): number {
  const utcFrom = Date.UTC(from.getFullYear(), from.getMonth(), from.getDate())
  const utcTo = Date.UTC(to.getFullYear(), to.getMonth(), to.getDate())

  return Math.round((utcTo - utcFrom) / MS_PER_DAY)
}

/** The dates a preset applies, computed from the local calendar of `now`. */
export function presetRange(preset: DashboardPeriodPreset, now: Date = new Date()): DashboardDateRange {
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())

  switch (preset) {
    case 'today':
      return { date_from: toLocalIsoDate(today), date_to: toLocalIsoDate(today) }
    case 'yesterday': {
      const yesterday = toLocalIsoDate(shiftDays(today, -1))
      return { date_from: yesterday, date_to: yesterday }
    }
    case 'last7Days':
      return { date_from: toLocalIsoDate(shiftDays(today, 1 - LAST_DAYS_SPAN)), date_to: toLocalIsoDate(today) }
    case 'thisMonth':
      return {
        date_from: toLocalIsoDate(new Date(today.getFullYear(), today.getMonth(), 1)),
        date_to: toLocalIsoDate(today),
      }
    case 'lastMonth':
      return {
        date_from: toLocalIsoDate(new Date(today.getFullYear(), today.getMonth() - 1, 1)),
        // Day 0 of this month is the last day of the previous one.
        date_to: toLocalIsoDate(new Date(today.getFullYear(), today.getMonth(), 0)),
      }
    case 'all':
      return { date_from: '', date_to: '' }
  }
}

/**
 * The preset the applied dates correspond to, or null for a custom range
 * (D-2: derived, never stored, so it cannot disagree with the filters).
 */
export function activePreset(range: DashboardDateRange, now: Date = new Date()): DashboardPeriodPreset | null {
  return (
    DASHBOARD_PERIOD_PRESETS.find((preset) => {
      const candidate = presetRange(preset, now)
      return candidate.date_from === range.date_from && candidate.date_to === range.date_to
    }) ?? null
  )
}

/**
 * The period of the same length that ends the day before `date_from`
 * (D-4), or null when either bound is open: an unbounded period has no
 * "previous" of equal length.
 */
export function previousPeriod(range: DashboardDateRange): DashboardDateRange | null {
  if (range.date_from === '' || range.date_to === '') {
    return null
  }

  const from = parseLocalIsoDate(range.date_from)
  const span = daysBetween(from, parseLocalIsoDate(range.date_to))
  const previousTo = shiftDays(from, -1)

  return { date_from: toLocalIsoDate(shiftDays(previousTo, -span)), date_to: toLocalIsoDate(previousTo) }
}
