import i18n from '@/i18n'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'

/** Formats a server decimal string for display; the frontend never recomputes it. */
export function money(value: string): string {
  return formatQuoteAmount(Number(value))
}

/** Quantity and commission rate: trailing zeros dropped ("2.00" -> "2", "10.0000" -> "10"). */
export function plainNumber(value: string): string {
  return new Intl.NumberFormat(i18n.language, { maximumFractionDigits: 4 }).format(Number(value))
}
