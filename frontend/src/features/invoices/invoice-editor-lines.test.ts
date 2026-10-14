import { describe, expect, it } from 'vitest'
import { amountsOf, newLine } from '@/features/invoices/invoice-editor-lines'

const REFS = {
  1: { id: 1, name: 'IVA 22%', rate: '22.00' },
  2: { id: 2, name: 'IVA 10%', rate: '10.00' },
}

describe('invoice editor amounts (spec 0253 AC-010)', () => {
  it('computes the VAT once per rate: three rows of 10.01 at 22% give 6.61, cent on the first row', () => {
    const rows = [1, 2, 3].map(() => newLine({ description: 'A', unit_price: 10.01, vat_rate_id: 1 }))

    const { lines, totals } = amountsOf(rows, REFS)

    expect(totals).toEqual({ net: 3003, vat: 661, total: 3664 })
    expect(lines.map((line) => line.vat)).toEqual([221, 220, 220])
  })

  it('keeps the rates independent and treats a row without VAT as zero VAT', () => {
    const rows = [
      newLine({ description: 'A', quantity: 2, unit_price: 10, vat_rate_id: 1 }),
      newLine({ description: 'B', unit_price: 5.55, vat_rate_id: 2 }),
      newLine({ description: 'C', quantity: 3, unit_price: 1.99 }),
    ]

    expect(amountsOf(rows, REFS).totals).toEqual({ net: 3152, vat: 496, total: 3648 })
  })
})
