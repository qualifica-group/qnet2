import { beforeAll, describe, expect, it } from 'vitest'
import { WORKFLOW_STATUS_OPEN, WORKFLOW_STATUS_REQUIRES_NOTE } from '@/features/quotes/quote-fixtures'
import i18n from '@/i18n'
import { buildCreateQuoteSchema, buildUpdateQuoteSchema, MAX_MANAGERS } from '@/features/quotes/quote-schema'

/**
 * Spec 0065 AC-070/075: create requires the identity fields; each
 * `offer_lines`/`cost_lines` row is validated on its own (quantity > 0, price
 * >= 0, max 2 decimals), the error landing on that exact row/field so the
 * form can wire `aria-describedby`/`aria-invalid` per field (AC-075).
 */

function validLine(overrides: Record<string, unknown> = {}) {
  return {
    product_id: 1,
    quantity: 3,
    unit_price: 10,
    vat_rate_id: null,
    ...overrides,
  }
}

function baseValues(overrides: Record<string, unknown> = {}) {
  return {
    code: 'QUO-0001',
    title: 'Offerta cliente Acme',
    opportunity_id: 1,
    quote_workflow_status_id: null,
    note: null,
    commercial_id: null,
    reporter_id: null,
    supervisor_id: null,
    manager_slots: [],
    company_id: null,
    company_site_id: null,
    operational_site_id: null,
    layout_id: null,
    payment_method_id: null,
    internal_notes: null,
    rewards: [],
    attribute_values: {},
    offer_lines: [],
    cost_lines: [],
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildCreateQuoteSchema', () => {
  it('accepts a valid payload with empty line collections', () => {
    const schema = buildCreateQuoteSchema(i18n.t)
    const result = schema.safeParse(baseValues())
    expect(result.success).toBe(true)
  })

  it('rejects an empty title', () => {
    const schema = buildCreateQuoteSchema(i18n.t)
    const result = schema.safeParse(baseValues({ title: '' }))
    expect(result.success).toBe(false)
  })

  it('rejects a title over 191 characters', () => {
    const schema = buildCreateQuoteSchema(i18n.t)
    const result = schema.safeParse(baseValues({ title: 'a'.repeat(192) }))
    expect(result.success).toBe(false)
  })

  it('rejects a code over 32 characters', () => {
    const schema = buildCreateQuoteSchema(i18n.t)
    const result = schema.safeParse(baseValues({ code: 'a'.repeat(33) }))
    expect(result.success).toBe(false)
  })

  it('rejects an empty code (the create form always auto-fills one)', () => {
    const schema = buildCreateQuoteSchema(i18n.t)
    const result = schema.safeParse(baseValues({ code: '' }))
    expect(result.success).toBe(false)
  })

  it('rejects a missing opportunity_id', () => {
    const schema = buildCreateQuoteSchema(i18n.t)
    const result = schema.safeParse(baseValues({ opportunity_id: null }))
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'opportunity_id')).toBe(true)
    }
  })

  it('rejects an internal_notes over 5000 characters', () => {
    const schema = buildCreateQuoteSchema(i18n.t)
    const result = schema.safeParse(baseValues({ internal_notes: 'a'.repeat(5001) }))
    expect(result.success).toBe(false)
  })

  it('accepts a valid offer line', () => {
    const schema = buildCreateQuoteSchema(i18n.t)
    const result = schema.safeParse(baseValues({ offer_lines: [validLine()] }))
    expect(result.success).toBe(true)
  })

  it('rejects more than 200 lines in a single tab (AC-035)', () => {
    const schema = buildCreateQuoteSchema(i18n.t)
    const lines = Array.from({ length: 201 }, () => validLine())
    const result = schema.safeParse(baseValues({ offer_lines: lines }))
    expect(result.success).toBe(false)
  })

  it(`accepts exactly ${MAX_MANAGERS} filled manager slots (spec 0087 D-1)`, () => {
    const schema = buildCreateQuoteSchema(i18n.t)
    const filled = Array.from({ length: MAX_MANAGERS }, (_, index) => index + 1)
    const result = schema.safeParse(baseValues({ manager_slots: filled }))
    expect(result.success).toBe(true)
  })

  it(`rejects more than ${MAX_MANAGERS} filled manager slots (spec 0087 D-1)`, () => {
    const schema = buildCreateQuoteSchema(i18n.t)
    const overflowing = Array.from({ length: MAX_MANAGERS + 1 }, (_, index) => index + 1)
    const result = schema.safeParse(baseValues({ manager_slots: overflowing }))
    expect(result.success).toBe(false)
  })

  describe('per-row validation (AC-034/AC-075)', () => {
    it.each([0, -1])('rejects quantity %s, error path on that row', (quantity) => {
      const schema = buildCreateQuoteSchema(i18n.t)
      const result = schema.safeParse(baseValues({ offer_lines: [validLine({ quantity })] }))
      expect(result.success).toBe(false)
      if (!result.success) {
        expect(
          result.error.issues.some((issue) => issue.path.join('.') === 'offer_lines.0.quantity'),
        ).toBe(true)
      }
    })

    it('rejects a missing product_id, error path on that row', () => {
      const schema = buildCreateQuoteSchema(i18n.t)
      const result = schema.safeParse(baseValues({ offer_lines: [validLine({ product_id: null })] }))
      expect(result.success).toBe(false)
      if (!result.success) {
        expect(
          result.error.issues.some((issue) => issue.path.join('.') === 'offer_lines.0.product_id'),
        ).toBe(true)
      }
    })

    // Direttiva 2026-09-01: le create form aprono su una riga vuota, quindi
    // una riga MAI toccata non deve bloccare il submit; basta un solo campo
    // compilato perche' le regole `required` tornino a valere.
    it('accepts an untouched empty row', () => {
      const schema = buildCreateQuoteSchema(i18n.t)
      const emptyRow = { product_id: null, quantity: null, unit_price: null, vat_rate_id: null, commissions: [] }
      expect(schema.safeParse(baseValues({ offer_lines: [emptyRow] })).success).toBe(true)
    })

    it('rejects a partially filled row (no longer untouched)', () => {
      const schema = buildCreateQuoteSchema(i18n.t)
      const startedRow = { product_id: null, quantity: 2, unit_price: null, vat_rate_id: null, commissions: [] }
      const result = schema.safeParse(baseValues({ offer_lines: [startedRow] }))
      expect(result.success).toBe(false)
      if (!result.success) {
        expect(
          result.error.issues.some((issue) => issue.path.join('.') === 'offer_lines.0.product_id'),
        ).toBe(true)
      }
    })

    it('rejects a negative unit_price, error path on that row', () => {
      const schema = buildCreateQuoteSchema(i18n.t)
      const result = schema.safeParse(baseValues({ cost_lines: [validLine({ unit_price: -1 })] }))
      expect(result.success).toBe(false)
      if (!result.success) {
        expect(
          result.error.issues.some((issue) => issue.path.join('.') === 'cost_lines.0.unit_price'),
        ).toBe(true)
      }
    })

    it('accepts unit_price 0 (AC-034)', () => {
      const schema = buildCreateQuoteSchema(i18n.t)
      const result = schema.safeParse(baseValues({ offer_lines: [validLine({ unit_price: 0 })] }))
      expect(result.success).toBe(true)
    })

    it('rejects a quantity with more than 2 decimals (AC-032)', () => {
      const schema = buildCreateQuoteSchema(i18n.t)
      const result = schema.safeParse(baseValues({ offer_lines: [validLine({ quantity: 10.005 })] }))
      expect(result.success).toBe(false)
      if (!result.success) {
        expect(
          result.error.issues.some((issue) => issue.path.join('.') === 'offer_lines.0.quantity'),
        ).toBe(true)
      }
    })

    it('accepts a unit_price with exactly 2 decimals (AC-032)', () => {
      const schema = buildCreateQuoteSchema(i18n.t)
      const result = schema.safeParse(baseValues({ offer_lines: [validLine({ unit_price: 10.01 })] }))
      expect(result.success).toBe(true)
    })
  })
})

describe('buildUpdateQuoteSchema', () => {
  const STATUSES = [WORKFLOW_STATUS_OPEN, WORKFLOW_STATUS_REQUIRES_NOTE]

  it('has the same shape as the create schema', () => {
    const schema = buildUpdateQuoteSchema(i18n.t, STATUSES, WORKFLOW_STATUS_OPEN.id)
    const result = schema.safeParse(baseValues({ offer_lines: [validLine()] }))
    expect(result.success).toBe(true)
  })

  // AC-051 / AC-023: the note is demanded by the TRANSITION, not by the row.
  it('rejects a move onto a requires_note status with no note', () => {
    const schema = buildUpdateQuoteSchema(i18n.t, STATUSES, WORKFLOW_STATUS_OPEN.id)
    const result = schema.safeParse(
      baseValues({
        offer_lines: [validLine()],
        quote_workflow_status_id: WORKFLOW_STATUS_REQUIRES_NOTE.id,
        note: '   ',
      }),
    )

    expect(result.success).toBe(false)
    expect(result.error?.issues.some((issue) => issue.path.join('.') === 'note')).toBe(true)
  })

  it('accepts the same move once a note is supplied', () => {
    const schema = buildUpdateQuoteSchema(i18n.t, STATUSES, WORKFLOW_STATUS_OPEN.id)
    const result = schema.safeParse(
      baseValues({
        offer_lines: [validLine()],
        quote_workflow_status_id: WORKFLOW_STATUS_REQUIRES_NOTE.id,
        note: 'Offerta accettata dal cliente',
      }),
    )

    expect(result.success).toBe(true)
  })

  // AC-026: re-saving a quote ALREADY parked on that row demands nothing.
  it('does not demand a note when the status is unchanged', () => {
    const schema = buildUpdateQuoteSchema(i18n.t, STATUSES, WORKFLOW_STATUS_REQUIRES_NOTE.id)
    const result = schema.safeParse(
      baseValues({
        offer_lines: [validLine()],
        quote_workflow_status_id: WORKFLOW_STATUS_REQUIRES_NOTE.id,
        note: null,
      }),
    )

    expect(result.success).toBe(true)
  })
})
