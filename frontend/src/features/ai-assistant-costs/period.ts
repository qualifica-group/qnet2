import type { AiCostPeriod } from '@/features/ai-assistant-costs/types'

function isoDate(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

/** From the first of the month up to today: where the page starts. */
export function currentMonthPeriod(now: Date = new Date()): AiCostPeriod {
  return { from: isoDate(new Date(now.getFullYear(), now.getMonth(), 1)), to: isoDate(now) }
}

export function today(now: Date = new Date()): string {
  return isoDate(now)
}

/** `YYYY-MM-DD` strings compare in calendar order. */
export function isValidPeriod({ from, to }: AiCostPeriod): boolean {
  return from !== '' && to !== '' && from <= to
}
