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
