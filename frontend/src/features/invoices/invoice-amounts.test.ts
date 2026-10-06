import { describe, expect, it } from 'vitest'
import {
  computeInvoiceTotals,
  computeLineAmounts,
  EMPTY_INVOICE_TOTALS,
  round2,
  toCents,
} from '@/features/invoices/invoice-amounts'

describe('invoice-amounts (spec 0194 preview)', () => {
  it('rounds half away from zero without float drift', () => {
    expect(toCents(1.005)).toBe(101)
    expect(toCents(-1.005)).toBe(-101)
    expect(round2(2.675)).toBe(2.68)
  })

  it('computes net, vat and total of a line in cents', () => {
    expect(computeLineAmounts({ quantity: 3, unitPrice: 10.1, vatRatePercent: 22 })).toEqual({
      net: 3030,
      vat: 667,
      total: 3697,
    })
  })

  it('treats a missing VAT rate as zero VAT', () => {
    expect(computeLineAmounts({ quantity: 2, unitPrice: 5, vatRatePercent: null })).toEqual({
      net: 1000,
      vat: 0,
      total: 1000,
    })
  })

  it('sums lines per-line-rounded, and is zero for no lines', () => {
    const lines = [
      { quantity: 1, unitPrice: 0.05, vatRatePercent: 22 },
      { quantity: 1, unitPrice: 0.05, vatRatePercent: 22 },
    ]
    expect(computeInvoiceTotals(lines)).toEqual({ net: 10, vat: 2, total: 12 })
    expect(computeInvoiceTotals([])).toEqual(EMPTY_INVOICE_TOTALS)
  })
})
