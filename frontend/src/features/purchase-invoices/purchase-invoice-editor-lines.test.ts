import { describe, expect, it } from 'vitest'
import {
  buildWritePayload,
  mapServerErrors,
  newLine,
  NO_ID,
  toLineWrite,
  amountsOf,
} from '@/features/purchase-invoices/purchase-invoice-editor-lines'
import type { PurchaseInvoiceWriteFormValues } from '@/features/purchase-invoices/purchase-invoice-schema'

const NO_DEFAULTS = { cost_type_id: null, work_order_id: null }

function formValues(overrides: Partial<PurchaseInvoiceWriteFormValues> = {}): PurchaseInvoiceWriteFormValues {
  return {
    document_type: 'proforma',
    document_number: ' ignored ',
    document_date: '2026-10-13',
    company_id: 1,
    supplier_id: 2,
    payment_method_id: 3,
    financial_account_id: null,
    company_site_id: null,
    operational_site_id: null,
    cost_type_id: null,
    work_order_id: null,
    notes: '  ',
    purchase_order_ids: [],
    lines: [{ ...newLine({ description: 'A', quantity: 1, unit_price: 10, cost_type_id: 4 }, NO_DEFAULTS), id: 9 }],
    ...overrides,
  }
}

describe('purchase invoice editor lines', () => {
  it('applies the header defaults to a new row, leaving the cost type unset without one', () => {
    expect(newLine({ description: 'X' }, { cost_type_id: 5, work_order_id: 6 })).toMatchObject({
      cost_type_id: 5,
      work_order_id: 6,
      quantity: 1,
    })
    expect(newLine({ description: 'X' }, NO_DEFAULTS).cost_type_id).toBe(NO_ID)
  })

  it('spec 0251 AC-009: a catalog product cost type applies only when the header gave none', () => {
    expect(newLine({ description: 'X', cost_type_id: 8 }, NO_DEFAULTS).cost_type_id).toBe(8)
    expect(newLine({ description: 'X', cost_type_id: 8 }, { cost_type_id: 5, work_order_id: null }).cost_type_id).toBe(5)
  })

  it('sums rows with 3-decimal quantities and treats a missing VAT as zero', () => {
    const rows = [
      newLine({ description: 'A', quantity: 2.5, unit_price: 100, vat_rate_id: 1, cost_type_id: 1 }, NO_DEFAULTS),
      newLine({ description: 'B', quantity: 0.333, unit_price: 10, cost_type_id: 1 }, NO_DEFAULTS),
    ]

    expect(amountsOf(rows, { 1: { id: 1, name: 'IVA', rate: '22.00' } }).totals).toEqual({ net: 25333, vat: 5500, total: 30833 })
  })

  it('spec 0253 AC-010: VAT is computed once per rate and split over the rows', () => {
    const refs = { 1: { id: 1, name: 'IVA', rate: '22.00' } }
    const rows = [1, 2, 3].map(() => newLine({ description: 'A', unit_price: 10.01, vat_rate_id: 1, cost_type_id: 1 }, NO_DEFAULTS))

    const { lines, totals } = amountsOf(rows, refs)

    expect(totals.vat).toBe(661)
    expect(lines.map((line) => line.vat)).toEqual([221, 220, 220])
  })

  // Spec 0263 (REQUIREMENT CHANGED): the editor only creates; saved lines travel from the record via toLineWrite.
  it('sends type and number at creation and nulls empty notes', () => {
    expect(buildWritePayload(formValues()).notes).toBeNull()

    const create = buildWritePayload(formValues({ document_type: 'invoice', document_number: ' FA-1 ' }))
    expect(create).toMatchObject({ document_type: 'invoice', document_number: 'FA-1' })
    expect(buildWritePayload(formValues()).document_number).toBeNull()
  })

  it('keeps the persisted row id and never sends the client-only billable limit', () => {
    const line = toLineWrite({ ...formValues().lines[0]!, available_quantity: 2 })
    expect(line.id).toBe(9)
    expect(line).not.toHaveProperty('available_quantity')
  })

  it('maps server keys to row inputs, an invoiced ODA row to its description, and the rest to general messages', () => {
    const mapped = mapServerErrors({
      document_number: ['dup'],
      'lines.2.unit_price': ['bad price'],
      'lines.1.purchase_order_line_id': ['already invoiced'],
      'purchase_order_ids.0': ['other supplier'],
    })

    expect(mapped.fields).toEqual([
      { path: 'document_number', message: 'dup' },
      { path: 'lines.2.unit_price', message: 'bad price' },
      { path: 'lines.1.description', message: 'already invoiced' },
    ])
    expect(mapped.general).toEqual(['other supplier'])
  })
})
