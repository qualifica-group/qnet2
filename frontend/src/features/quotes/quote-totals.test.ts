import { describe, expect, it } from 'vitest'
import {
  aggregateLines,
  computeLineAmounts,
  computeQuoteTotals,
  round2,
  type QuoteLineForTotals,
} from '@/features/quotes/quote-totals'

/** Spec 0065 D-5/D-9/D-12, AC-030/031/032/040/042/043: client-side replica of the server calculation. */

describe('round2', () => {
  it('rounds half-up despite float artifacts (10.005 * ... style errors)', () => {
    // 1.005 * 100 === 100.49999999999999 under plain multiplication; round2
    // must still resolve to 1.01, not 1.00.
    expect(round2(1.005)).toBe(1.01)
  })

  it('leaves an already-clean 2-decimal value untouched', () => {
    expect(round2(36.6)).toBe(36.6)
  })

  it('rounds a negative value correctly (margin can be negative, AC-043)', () => {
    expect(round2(-9.995)).toBeCloseTo(-9.99, 5)
  })
})

describe('computeLineAmounts (AC-030/031/032)', () => {
  it('computes net/vat/total for quantity 3, unit_price 10.00, 22% VAT', () => {
    expect(computeLineAmounts(3, 10, 22)).toEqual({ net: 30, vat: 6.6, total: 36.6 })
  })

  it('vat is 0 and total equals net when there is no VAT rate', () => {
    expect(computeLineAmounts(3, 10, null)).toEqual({ net: 30, vat: 0, total: 30 })
  })

  it('rounds half-up: quantity 3, unit_price 10.01 -> net 30.03 (AC-032)', () => {
    expect(computeLineAmounts(3, 10.01, null).net).toBe(30.03)
  })
})

describe('aggregateLines', () => {
  it('sums the already-rounded per-row amounts (D-12)', () => {
    const lines: QuoteLineForTotals[] = [
      { quantity: 3, unitPrice: 10, vatRatePercent: 22 },
      { quantity: 1, unitPrice: 5, vatRatePercent: null },
    ]
    // Row 1: net 30.00, vat 6.60. Row 2: net 5.00, vat 0.00.
    expect(aggregateLines(lines)).toEqual({ net: 35, vat: 6.6, gross: 41.6 })
  })

  it('returns all zeros for an empty collection (AC-042)', () => {
    expect(aggregateLines([])).toEqual({ net: 0, vat: 0, gross: 0 })
  })
})

describe('computeQuoteTotals (D-5, AC-040/042/043)', () => {
  it('exposes revenue/cost side by side and the margin on the imponibile', () => {
    const offerLines: QuoteLineForTotals[] = [{ quantity: 3, unitPrice: 10, vatRatePercent: 22 }]
    const costLines: QuoteLineForTotals[] = [{ quantity: 1, unitPrice: 10, vatRatePercent: 22 }]

    const totals = computeQuoteTotals(offerLines, costLines)

    expect(totals.revenue).toEqual({ net: 30, vat: 6.6, gross: 36.6 })
    expect(totals.cost).toEqual({ net: 10, vat: 2.2, gross: 12.2 })
    expect(totals.margin).toEqual({ net: 20 })
  })

  it('exposes all zeros for a quote with no rows (AC-042)', () => {
    const totals = computeQuoteTotals([], [])
    expect(totals).toEqual({
      revenue: { net: 0, vat: 0, gross: 0 },
      cost: { net: 0, vat: 0, gross: 0 },
      margin: { net: 0 },
    })
  })

  it('returns a negative margin, unclamped, when costs exceed revenue (AC-043)', () => {
    const offerLines: QuoteLineForTotals[] = [{ quantity: 1, unitPrice: 10, vatRatePercent: null }]
    const costLines: QuoteLineForTotals[] = [{ quantity: 1, unitPrice: 30, vatRatePercent: null }]

    const totals = computeQuoteTotals(offerLines, costLines)

    expect(totals.margin.net).toBe(-20)
  })
})
