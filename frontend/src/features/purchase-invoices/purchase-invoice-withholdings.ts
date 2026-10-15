import { toCents } from '@/lib/amounts/line-amounts'

/** Combined withholding tax and ENASARCO, in cents (empty = 0). */
export function withholdingsCents(withholding: number | null, enasarco: number | null): number {
  return toCents(withholding ?? 0) + toCents(enasarco ?? 0)
}

/** The deductions must leave something to pay: the server answers 422 on withholding_amount otherwise (spec 0267 D-6). */
export function withholdingsWithinTotal(deductionsCents: number, totalCents: number): boolean {
  return deductionsCents === 0 || deductionsCents < totalCents
}

/** Server amount ("12.50", possibly absent on older payloads) as a form value: zero means empty. */
export function amountOrNull(value: string | undefined): number | null {
  const amount = Number(value ?? 0)
  return Number.isFinite(amount) && amount !== 0 ? amount : null
}
