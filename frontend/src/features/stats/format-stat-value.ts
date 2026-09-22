import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import type { StatValueFormat } from '@/features/stats/types'

/** Single currency of the application; the backend always sends plain numbers. */
const CURRENCY_CODE = 'EUR'
const PERCENT_MAX_FRACTION_DIGITS = 1

/** Rendered instead of a value the backend could not compute (percent with a 0 denominator). */
export const EMPTY_STAT_VALUE = '—'

function formatNumber(value: number, locale: string): string {
  return new Intl.NumberFormat(locale).format(value)
}

function formatCurrency(value: number, locale: string): string {
  return new Intl.NumberFormat(locale, { style: 'currency', currency: CURRENCY_CODE }).format(value)
}

function formatPercent(value: number, locale: string): string {
  const formatted = new Intl.NumberFormat(locale, {
    maximumFractionDigits: PERCENT_MAX_FRACTION_DIGITS,
  }).format(value)

  return `${formatted}%`
}

/**
 * Single formatting entry point of the statistics panel (spec 0026). `null` is
 * never rendered as `0`: a missing value shows the placeholder, so a percent
 * with a 0 denominator can never read "0%".
 */
export function formatStatValue(
  value: number | null,
  format: StatValueFormat,
  locale: string,
): string {
  if (value === null) {
    return EMPTY_STAT_VALUE
  }

  switch (format) {
    case 'currency':
      return formatCurrency(value, locale)
    case 'percent':
      return formatPercent(value, locale)
    case 'duration':
      return formatMinutesLabel(value)
    default:
      return formatNumber(value, locale)
  }
}

/** Formatter handed to the bar/chart widgets, which only ever render numbers. */
export function statSeriesFormatter(
  format: StatValueFormat,
  locale: string,
): (value: number) => string {
  return (value: number) => formatStatValue(value, format, locale)
}
