import { EMPTY_AMOUNTS_CENTS, fromCents, lineAmounts, round2, type AmountsCents } from '@/lib/amounts/line-amounts'

/**
 * Client-side PREVIEW of the RDA amounts (spec 0208 D-12): the server always
 * recomputes. VAT is per line (not fiscal documents, spec 0253 D-2), with the
 * shared integer-cents engine.
 */

/** Quantity decimals of RDA and ODA lines. */
export const PURCHASE_QUANTITY_SCALE = 3

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

function toAmounts(cents: AmountsCents): PurchaseAmounts {
  return { taxable: fromCents(cents.net), vat: fromCents(cents.vat), total: fromCents(cents.total) }
}

function lineCents(line: AmountLine): AmountsCents {
  return lineAmounts(line.quantity, line.unit_price, line.vat_rate_percent, PURCHASE_QUANTITY_SCALE)
}

export function previewLineAmounts(line: AmountLine): PurchaseAmounts {
  return toAmounts(lineCents(line))
}

export function previewTotals(lines: readonly AmountLine[]): PurchaseAmounts {
  return toAmounts(
    lines.reduce<AmountsCents>((acc, line) => {
      const cents = lineCents(line)
      return { net: acc.net + cents.net, vat: acc.vat + cents.vat, total: acc.total + cents.total }
    }, EMPTY_AMOUNTS_CENTS),
  )
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
