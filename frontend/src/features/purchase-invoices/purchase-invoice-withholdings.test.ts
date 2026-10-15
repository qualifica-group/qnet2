import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildWritePayload } from '@/features/purchase-invoices/purchase-invoice-editor-lines'
import { blankSource, valuesFromInvoice } from '@/features/purchase-invoices/purchase-invoice-editor-source'
import { buildPurchaseInvoiceWriteSchema } from '@/features/purchase-invoices/purchase-invoice-schema'
import { lockedForSettlement } from '@/features/purchase-invoices/purchase-invoice-settlement-lock'
import { makePurchaseInvoiceView } from '@/features/purchase-invoices/purchase-invoice-view-fixtures'
import {
  amountOrNull,
  withholdingsCents,
  withholdingsWithinTotal,
} from '@/features/purchase-invoices/purchase-invoice-withholdings'

const schema = buildPurchaseInvoiceWriteSchema(i18n.t.bind(i18n))

function valid(overrides: Record<string, unknown>) {
  const values = valuesFromInvoice(makePurchaseInvoiceView({ document_type: 'invoice', document_number: 'FT-1' }))
  return schema.safeParse({ ...values, ...overrides })
}

describe('withholdings (spec 0267 AC-023)', () => {
  it('accepts empty and positive deductions and rejects negative ones on their field', () => {
    expect(valid({ withholding_amount: null, enasarco_amount: null }).success).toBe(true)
    expect(valid({ withholding_amount: 115, enasarco_amount: 85.5 }).success).toBe(true)

    const negative = valid({ withholding_amount: -1 })
    expect(negative.success).toBe(false)
    expect(negative.error?.issues[0]?.path).toEqual(['withholding_amount'])
  })

  it('requires the deductions to stay below the total, empty counting as zero', () => {
    expect(withholdingsCents(115, 85)).toBe(20000)
    expect(withholdingsCents(null, null)).toBe(0)
    expect(withholdingsWithinTotal(20000, 122000)).toBe(true)
    expect(withholdingsWithinTotal(122000, 122000)).toBe(false)
    expect(withholdingsWithinTotal(0, 0)).toBe(true)
  })

  it('reads server amounts as empty when zero or absent', () => {
    expect(amountOrNull('0.00')).toBeNull()
    expect(amountOrNull(undefined)).toBeNull()
    expect(amountOrNull('115.00')).toBe(115)
  })

  it('sends the deductions only when filled', () => {
    const base = { ...blankSource().initialValues, document_number: 'FT-1' }

    const empty = buildWritePayload(base)
    expect(empty).not.toHaveProperty('withholding_amount')
    expect(empty).not.toHaveProperty('enasarco_amount')

    const filled = buildWritePayload({ ...base, withholding_amount: 115, enasarco_amount: 85 })
    expect(filled).toMatchObject({ withholding_amount: 115, enasarco_amount: 85 })
  })

  it('locks update and delete of a document generated from a settlement, and leaves others untouched', () => {
    const free = makePurchaseInvoiceView()
    expect(lockedForSettlement(free)).toBe(free)

    const linked = lockedForSettlement(makePurchaseInvoiceView({ commission_settlement: { id: 9, code: '3/2026' } }))
    expect(linked.permissions.actions).toMatchObject({ update: false, delete: false, details: true })
  })
})
