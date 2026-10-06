/**
 * Client-side PREVIEW of the document amounts (spec 0194 D-9): the server
 * recomputes everything on save. Integer-cents math so sums never accumulate
 * float noise; every rounding is half away from zero, matching the server.
 */

/** Per-line amounts in cents. */
export interface LineAmountsCents {
  net: number
  vat: number
  total: number
}

/** Document totals in cents. */
export interface InvoiceTotalsCents {
  net: number
  vat: number
  total: number
}

/** Minimal line shape the preview needs (decoupled from the form's own row type). */
export interface PreviewLine {
  quantity: number
  unitPrice: number
  /** VAT rate percentage (22 for 22%), or null when none is selected. */
  vatRatePercent: number | null
}

export const EMPTY_INVOICE_TOTALS: InvoiceTotalsCents = { net: 0, vat: 0, total: 0 }

/** Rounds to an integer half away from zero, via the decimal string to avoid 1.005 * 100 drift. */
function roundHalfAwayFromZero(value: number, decimalShift: number): number {
  const sign = value < 0 ? -1 : 1
  const abs = Math.abs(value)
  // Exponent-notation strings ("1e-7") cannot take another exponent suffix: fall back to plain math.
  const shifted = Number(`${abs}e${decimalShift}`)
  return sign * Math.round(Number.isNaN(shifted) ? abs * 10 ** decimalShift : shifted)
}

/** Decimal amount -> integer cents. */
export function toCents(amount: number): number {
  return roundHalfAwayFromZero(amount, 2)
}

/** Integer cents -> decimal amount with 2 decimals of precision. */
export function fromCents(cents: number): number {
  return cents / 100
}

/** Rounds a decimal amount to 2 decimals (half away from zero). */
export function round2(amount: number): number {
  return fromCents(toCents(amount))
}

/** net = round2(qty * price), vat = round2(net * rate / 100), total = net + vat, in cents. */
export function computeLineAmounts(line: PreviewLine): LineAmountsCents {
  const net = toCents(round2(line.quantity * line.unitPrice))
  const vat = line.vatRatePercent === null ? 0 : roundHalfAwayFromZero((net * line.vatRatePercent) / 100, 0)
  return { net, vat, total: net + vat }
}

/** Sums the per-line cents of every line. */
export function computeInvoiceTotals(lines: readonly PreviewLine[]): InvoiceTotalsCents {
  return lines.reduce<InvoiceTotalsCents>((acc, line) => {
    const amounts = computeLineAmounts(line)
    return {
      net: acc.net + amounts.net,
      vat: acc.vat + amounts.vat,
      total: acc.total + amounts.total,
    }
  }, EMPTY_INVOICE_TOTALS)
}
