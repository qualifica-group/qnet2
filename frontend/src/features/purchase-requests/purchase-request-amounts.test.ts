import { describe, expect, it } from 'vitest'
import {
  extractVat,
  MissingVatRateError,
  previewLineAmounts,
  previewTotals,
} from '@/features/purchase-requests/purchase-request-amounts'
import { buildPayload, emptyFormValues, newLineValues, toFormValues } from '@/features/purchase-requests/purchase-request-payload'
import { buildPurchaseRequestSchema } from '@/features/purchase-requests/purchase-request-schema'
import { makeLine, makeRequest } from '@/features/purchase-requests/purchase-request-fixtures'

const t = ((key: string) => key) as Parameters<typeof buildPurchaseRequestSchema>[0]

describe('purchase request amounts preview (AC-016)', () => {
  it('computes taxable, VAT and total of a line like the server (D-12)', () => {
    expect(previewLineAmounts({ quantity: 2, unit_price: 100, vat_rate_percent: 22 })).toEqual({
      taxable: 200,
      vat: 44,
      total: 244,
    })
    expect(previewLineAmounts({ quantity: 3, unit_price: 0.335, vat_rate_percent: 22 })).toEqual({
      taxable: 1.01,
      vat: 0.22,
      total: 1.23,
    })
  })

  it('treats a line without VAT rate as VAT free and a non-finite number as zero', () => {
    expect(previewLineAmounts({ quantity: 1, unit_price: 50, vat_rate_percent: null }).total).toBe(50)
    expect(previewLineAmounts({ quantity: Number.NaN, unit_price: 50, vat_rate_percent: 22 }).total).toBe(0)
  })

  it('sums the per-line amounts into the header totals', () => {
    const totals = previewTotals([
      { quantity: 2, unit_price: 100, vat_rate_percent: 22 },
      { quantity: 1, unit_price: 50.5, vat_rate_percent: 22 },
    ])
    expect(totals).toEqual({ taxable: 250.5, vat: 55.11, total: 305.61 })
  })

  it('extracts the VAT: 122.00 at 22% becomes 100.00', () => {
    expect(extractVat(122, 22)).toBe(100)
    expect(extractVat(10, 4)).toBe(9.62)
  })

  it('refuses to extract the VAT without a rate above zero', () => {
    expect(() => extractVat(122, null)).toThrow(MissingVatRateError)
    expect(() => extractVat(122, 0)).toThrow(MissingVatRateError)
  })
})

describe('purchase request payload (AC-016)', () => {
  it('builds an update payload that carries lines.*.id for persisted lines only', () => {
    const values = toFormValues(makeRequest())
    values.lines.push(newLineValues({ description: 'Mouse', quantity: 4, unit_price: 9.9 }))

    const payload = buildPayload(values)

    expect(payload.lines).toEqual([
      {
        id: 11,
        product_id: null,
        description: 'Laptop',
        reason: null,
        unit_of_measure_id: 1,
        quantity: 2,
        unit_price: 100,
        vat_rate_id: 1,
      },
      {
        product_id: null,
        description: 'Mouse',
        reason: null,
        unit_of_measure_id: null,
        quantity: 4,
        unit_price: 9.9,
        vat_rate_id: null,
      },
    ])
    expect(payload.lines[1]).not.toHaveProperty('id')
  })

  it('never sends totals, statuses or client-only helpers', () => {
    const payload = buildPayload(toFormValues(makeRequest()))
    expect(Object.keys(payload)).not.toEqual(expect.arrayContaining(['grand_total', 'status', 'pending_files']))
    expect(payload.lines[0]).not.toHaveProperty('status')
  })

  it('sends blank footer fields as null and keeps the header ids', () => {
    const payload = buildPayload(toFormValues(makeRequest({ notes: 'Urgent' })))
    expect(payload).toMatchObject({
      notes: 'Urgent',
      delivery_terms: null,
      requester_id: 3,
      function_manager_id: 4,
      company_id: 1,
      company_site_id: 2,
      operational_site_id: 3,
      business_function_id: 6,
    })
  })

  it('locks every line of a closed RDA and every non-pending line', () => {
    const closed = toFormValues(makeRequest({ status: 'closed' }))
    expect(closed.lines[0]?.locked).toBe(true)

    const mixed = toFormValues(
      makeRequest({ lines: [makeLine(), makeLine({ id: 12, status: 'approved' })] }),
    )
    expect(mixed.lines.map((line) => line.locked)).toEqual([false, true])
  })
})

describe('purchase request schema', () => {
  it('rejects an empty draft with the required-field messages', () => {
    const result = buildPurchaseRequestSchema(t).safeParse(emptyFormValues(null))
    expect(result.success).toBe(false)
    const paths = result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))
    expect(paths).toEqual(
      expect.arrayContaining([
        'subject',
        'requester_id',
        'function_manager_id',
        'company_id',
        'company_site_id',
        'operational_site_id',
        'business_function_id',
        'lines.0.description',
      ]),
    )
  })

  it('accepts a persisted request and rejects a zero quantity', () => {
    const schema = buildPurchaseRequestSchema(t)
    const values = toFormValues(makeRequest())
    expect(schema.safeParse(values).success).toBe(true)

    const zero = { ...values, lines: [{ ...values.lines[0]!, quantity: 0 }] }
    const result = schema.safeParse(zero)
    expect(result.success).toBe(false)
    expect(result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))).toContain(
      'lines.0.quantity',
    )
  })

  it('requires at least one line', () => {
    const values = { ...toFormValues(makeRequest()), lines: [] }
    const result = buildPurchaseRequestSchema(t).safeParse(values)
    expect(result.success).toBe(false)
  })
})
