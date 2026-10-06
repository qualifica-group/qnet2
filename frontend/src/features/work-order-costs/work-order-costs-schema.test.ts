// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { COST_LINE } from '@/features/work-order-costs/work-order-costs-fixtures'
import {
  buildWorkOrderCostsSchema,
  createEmptyCostRow,
  rowsFromCostLines,
  toCostsPayload,
  todayIsoDate,
  type WorkOrderCostRowValues,
} from '@/features/work-order-costs/work-order-costs-schema'

const schema = buildWorkOrderCostsSchema(i18n.t.bind(i18n))

function validRow(overrides: Partial<WorkOrderCostRowValues> = {}): WorkOrderCostRowValues {
  return { ...rowsFromCostLines([COST_LINE])[0], ...overrides }
}

function issuePaths(row: WorkOrderCostRowValues): string[] {
  const result = schema.safeParse({ lines: [row] })
  return result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))
}

afterEach(() => {
  vi.useRealTimers()
})

describe('workOrderCostRowSchema', () => {
  it('accepts a fully valid row', () => {
    expect(issuePaths(validRow())).toEqual([])
  })

  it('requires product, quantity and unit price on a fresh row', () => {
    expect(issuePaths(createEmptyCostRow())).toEqual(['lines.0.product_id', 'lines.0.quantity', 'lines.0.unit_price'])
  })

  it('rejects out-of-range and over-precise numbers like the quote rules', () => {
    expect(issuePaths(validRow({ quantity: 0 }))).toEqual(['lines.0.quantity'])
    expect(issuePaths(validRow({ quantity: 1.005 }))).toEqual(['lines.0.quantity'])
    expect(issuePaths(validRow({ unit_price: -1 }))).toEqual(['lines.0.unit_price'])
    expect(issuePaths(validRow({ unit_price: 100000000 }))).toEqual(['lines.0.unit_price'])
    expect(issuePaths(validRow({ unit_price: 0 }))).toEqual([])
  })

  it('rejects a missing or malformed incurred_on', () => {
    expect(issuePaths(validRow({ incurred_on: '' }))).toEqual(['lines.0.incurred_on'])
    expect(issuePaths(validRow({ incurred_on: '15/09/2026' }))).toEqual(['lines.0.incurred_on'])
  })

  it('enforces the text limits', () => {
    expect(issuePaths(validRow({ document_reference: 'x'.repeat(101) }))).toEqual(['lines.0.document_reference'])
    expect(issuePaths(validRow({ additional_description: 'x'.repeat(5001) }))).toEqual(['lines.0.additional_description'])
  })

  it('caps the set at 200 rows', () => {
    const result = schema.safeParse({ lines: Array.from({ length: 201 }, () => validRow()) })
    expect(result.success).toBe(false)
  })
})

describe('row mapping', () => {
  it('a new row defaults incurred_on to today (D-6)', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date(2026, 9, 2, 12, 0, 0))

    expect(todayIsoDate()).toBe('2026-10-02')
    expect(createEmptyCostRow().incurred_on).toBe('2026-10-02')
  })

  it('hydrates persisted lines ordered by sort_order with numeric inputs', () => {
    const second = { ...COST_LINE, id: 6, sort_order: 0 }
    const first = { ...COST_LINE, id: 5, sort_order: 1 }

    const rows = rowsFromCostLines([first, second])

    expect(rows.map((row) => row.id)).toEqual([6, 5])
    expect(rows[0]).toMatchObject({ quantity: 2, unit_price: 50, incurred_on: '2026-09-15' })
    expect(rows[0].display).toMatchObject({ vat_percent: 22, supplier_name: 'Fornitore Uno', unit_symbol: 'pz' })
  })

  it('builds the exact PUT payload: inputs only, no amounts, blank text as null', () => {
    const payload = toCostsPayload({
      lines: [validRow(), validRow({ id: undefined, document_reference: '  ', additional_description: '', quote_line_id: null })],
    })

    expect(payload).toEqual({
      lines: [
        {
          id: 5,
          product_id: 7,
          quantity: 2,
          unit_price: 50,
          vat_rate_id: 3,
          quote_line_id: 11,
          incurred_on: '2026-09-15',
          supplier_id: 40,
          document_reference: 'FT-12',
          additional_description: null,
        },
        {
          product_id: 7,
          quantity: 2,
          unit_price: 50,
          vat_rate_id: 3,
          quote_line_id: null,
          incurred_on: '2026-09-15',
          supplier_id: 40,
          document_reference: null,
          additional_description: null,
        },
      ],
    })
  })
})
