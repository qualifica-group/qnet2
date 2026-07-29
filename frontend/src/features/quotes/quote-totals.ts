/**
 * Client-side speculative replica of the server's economic calculation
 * (spec 0065 D-9/D-12): feeds the real-time summary preview (AC-071) with
 * ZERO network calls while the user edits a row. The server remains the sole
 * source of truth (D-9) — these functions must stay byte-for-byte equivalent
 * to the backend rounding rule, or the preview would drift from what gets
 * persisted on save.
 */

/** Per-row computed amounts, all already rounded half-up to 2 decimals. */
export interface QuoteLineAmounts {
  net: number
  vat: number
  total: number
}

/** One side (revenue/cost) of the aggregated summary. */
export interface QuoteAmountAggregate {
  net: number
  vat: number
  gross: number
}

/** The full real-time summary, mirroring `QuoteSummary`'s shape but as numbers. */
export interface QuoteTotalsSummary {
  revenue: QuoteAmountAggregate
  cost: QuoteAmountAggregate
  margin: { net: number }
}

/** The minimal per-row shape this module needs — decoupled from the form's own row type. */
export interface QuoteLineForTotals {
  quantity: number
  unitPrice: number
  /** The VAT rate PERCENTAGE (e.g. `22` for 22%), or `null` when the row has none. */
  vatRatePercent: number | null
}

/** Every aggregate/row amount starts here; a quote with no rows exposes all zeros (AC-042). */
export const EMPTY_QUOTE_TOTALS_SUMMARY: QuoteTotalsSummary = {
  revenue: { net: 0, vat: 0, gross: 0 },
  cost: { net: 0, vat: 0, gross: 0 },
  margin: { net: 0 },
}

/**
 * Rounds `value` half-up to 2 decimals (D-12). Shifts the decimal point via
 * the number's own exact, round-trip string form (exponential-notation
 * trick) instead of a raw `value * 100` multiplication: the latter
 * accumulates IEEE-754 representation error at exactly the 2-decimal-money
 * boundary this function exists to get right (e.g. `1.005 * 100` evaluates
 * to `100.49999999999999` in JS, which would floor to `1.00` instead of
 * rounding up to `1.01`). Every quote amount is non-negative at the row
 * level, so `Math.round`'s round-half-away-from-zero-for-positives behavior
 * is equivalent to "half-up" here; the margin (the only value that can go
 * negative) is a subtraction of two ALREADY-rounded numbers, so this call
 * only ever cleans up residual float noise on it, never re-rounds a genuine
 * half-way value.
 */
export function round2(value: number): number {
  return Number(`${Math.round(Number(`${value}e2`))}e-2`)
}

/**
 * Computes one row's `net`/`vat`/`total`, replicating `QuoteLine` server-side
 * exactly: `net = round(quantity * unit_price)`, `vat = round(net * rate / 100)`
 * (0 when no VAT rate), `total = net + vat` (D-12).
 */
export function computeLineAmounts(
  quantity: number,
  unitPrice: number,
  vatRatePercent: number | null,
): QuoteLineAmounts {
  const net = round2(quantity * unitPrice)
  const vat = vatRatePercent === null ? 0 : round2((net * vatRatePercent) / 100)
  const total = round2(net + vat)
  return { net, vat, total }
}

/**
 * Aggregates a tab's (offer or cost) rows: the sum of each row's ALREADY
 * ROUNDED `net`/`vat` (D-12 — never re-derived from the raw quantity/price),
 * with the sum itself rounded once at the end to absorb any float-addition
 * noise (e.g. summing several `x.x1` values).
 */
export function aggregateLines(lines: QuoteLineForTotals[]): QuoteAmountAggregate {
  let net = 0
  let vat = 0
  for (const line of lines) {
    const amounts = computeLineAmounts(line.quantity, line.unitPrice, line.vatRatePercent)
    net += amounts.net
    vat += amounts.vat
  }
  const roundedNet = round2(net)
  const roundedVat = round2(vat)
  return { net: roundedNet, vat: roundedVat, gross: round2(roundedNet + roundedVat) }
}

/**
 * Computes the full real-time summary (AC-071): revenue/cost breakdowns plus
 * the margin, calculated on the imponibile (D-5: `revenue.net - cost.net`,
 * never clamped to zero — AC-043).
 */
export function computeQuoteTotals(
  offerLines: QuoteLineForTotals[],
  costLines: QuoteLineForTotals[],
): QuoteTotalsSummary {
  const revenue = aggregateLines(offerLines)
  const cost = aggregateLines(costLines)
  return {
    revenue,
    cost,
    margin: { net: round2(revenue.net - cost.net) },
  }
}
