import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { buildInvoiceLineSchema } from '@/features/invoices/invoice-schema'

const t = ((key: string) => key) as TFunction
const LINE = { quote_line_id: null, product_id: null, description: 'A', quantity: 1, unit_price: 0.33, vat_rate_id: 1 }

describe('invoice line schema unit price (spec 0253 D-3)', () => {
  it('accepts at most 2 decimals', () => {
    expect(buildInvoiceLineSchema(t).safeParse(LINE).success).toBe(true)
  })

  it('rejects 3 decimals with the field message', () => {
    const result = buildInvoiceLineSchema(t).safeParse({ ...LINE, unit_price: 0.333 })

    expect(result.success).toBe(false)
    expect(result.error?.issues[0]).toMatchObject({
      path: ['unit_price'],
      message: 'invoiceEditor.errors.unitPriceDecimals',
    })
  })
})
