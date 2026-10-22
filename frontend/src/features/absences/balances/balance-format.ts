import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'

const MINUTES_PER_HOUR = 60
const HOURS_PATTERN = /^-?\d+([.,]\d+)?$/

/** Signed duration label: `formatMinutesLabel` clamps negatives to zero, a balance can be below it. */
export function formatSignedMinutes(minutes: number): string {
  return minutes < 0 ? `-${formatMinutesLabel(-minutes)}` : formatMinutesLabel(minutes)
}

/** True when the text is a decimal number of hours (`160`, `7.5`, `7,5`, `-8`). */
export function isHoursText(value: string): boolean {
  return HOURS_PATTERN.test(value.trim())
}

/** Decimal hours text -> whole minutes (the API unit). */
export function hoursTextToMinutes(value: string): number {
  return Math.round(Number(value.trim().replace(',', '.')) * MINUTES_PER_HOUR)
}

/** Whole minutes -> decimal hours text for the form fields (`450` -> `7.5`). */
export function minutesToHoursText(minutes: number): string {
  return String(Math.round((minutes / MINUTES_PER_HOUR) * 100) / 100)
}

/** Decimal days text -> whole minutes of the person's working day (`2.5` with `480` -> `1200`). */
export function daysTextToMinutes(value: string, dailyMinutes: number): number {
  return Math.round(Number(value.trim().replace(',', '.')) * dailyMinutes)
}

/** Whole minutes -> decimal days text for the form fields (`1200` with `480` -> `2.5`). */
export function minutesToDaysText(minutes: number, dailyMinutes: number): string {
  return String(Math.round((minutes / dailyMinutes) * 100) / 100)
}

const YEAR_OFFSETS = [-1, 0, 1] as const

/** Previous, current and next year, as options of the year switch. */
export function balanceYearOptions(currentYear: number): { value: string; label: string }[] {
  return YEAR_OFFSETS.map((offset) => ({ value: String(currentYear + offset), label: String(currentYear + offset) }))
}
