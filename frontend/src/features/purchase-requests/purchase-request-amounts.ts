import {
  computeInvoiceTotals,
  computeLineAmounts,
  fromCents,
  round2,
  type PreviewLine,
} from '@/features/invoices/invoice-amounts'

/**
 * Client-side PREVIEW of the RDA amounts (spec 0208 D-12): the server always
 * recomputes. Reuses the integer-cents math of the invoices preview, which
 * rounds half away from zero exactly like the backend.
 */

export interface PurchaseAmounts {
  taxable: number
  vat: number
  total: number
}

export const EMPTY_AMOUNTS: PurchaseAmounts = { taxable: 0, vat: 0, total: 0 }

export interface AmountLine {
  quantity: number
  unit_price: number
  /** Percentage of the line's VAT rate (22 for 22%), or null when none. */
  vat_rate_percent: number | null
}

function toPreview(line: AmountLine): PreviewLine {
  return {
    quantity: Number.isFinite(line.quantity) ? line.quantity : 0,
    unitPrice: Number.isFinite(line.unit_price) ? line.unit_price : 0,
    vatRatePercent: line.vat_rate_percent,
  }
}

export function previewLineAmounts(line: AmountLine): PurchaseAmounts {
  const cents = computeLineAmounts(toPreview(line))
  return { taxable: fromCents(cents.net), vat: fromCents(cents.vat), total: fromCents(cents.total) }
}

export function previewTotals(lines: readonly AmountLine[]): PurchaseAmounts {
  const cents = computeInvoiceTotals(lines.map(toPreview))
  return { taxable: fromCents(cents.net), vat: fromCents(cents.vat), total: fromCents(cents.total) }
}

/** Thrown by {@link extractVat} when the line has no VAT rate above zero. */
export class MissingVatRateError extends Error {
  constructor() {
    super('A VAT rate greater than zero is required to extract the VAT')
    this.name = 'MissingVatRateError'
  }
}

/** VAT extraction ("scorporo", D-12): unit_price = round(unit_price / (1 + rate / 100), 2). */
export function extractVat(unitPrice: number, ratePercent: number | null): number {
  if (ratePercent === null || !(ratePercent > 0)) {
    throw new MissingVatRateError()
  }
  return round2(unitPrice / (1 + ratePercent / 100))
}
