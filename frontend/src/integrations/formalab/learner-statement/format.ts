import { formatEuro } from '@/features/invoices/invoice-format'

const MINUTES_PER_HOUR = 60

const PERCENT_FORMAT = new Intl.NumberFormat('it-IT', { minimumFractionDigits: 1, maximumFractionDigits: 1 })

const RATE_FORMAT = new Intl.NumberFormat('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

/** Money as the legacy prospetto prints it (`€ 1.234,56`). */
export function euro(value: number): string {
  return formatEuro(value)
}

/** A tranche weight (0..1) as a percentage with one decimal: `38,1%`. */
export function weight(value: number): string {
  return `${PERCENT_FORMAT.format(value * 100)}%`
}

/** An hourly rate with two decimals, no currency sign. */
export function rate(value: number): string {
  return RATE_FORMAT.format(value)
}

/** Decimal hours (quarters) as whole hours and minutes: 381.25 -> [381, 15]. */
export function splitHours(value: number): [number, number] {
  const whole = Math.floor(value)
  return [whole, Math.round((value - whole) * MINUTES_PER_HOUR)]
}

/** Minutes as whole hours and the rest: 125 -> [2, 5]. */
export function splitMinutes(value: number): [number, number] {
  return [Math.floor(value / MINUTES_PER_HOUR), value % MINUTES_PER_HOUR]
}
