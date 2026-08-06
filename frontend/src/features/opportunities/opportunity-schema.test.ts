import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateOpportunitySchema,
  buildUpdateOpportunitySchema,
  MAX_MANAGERS,
} from '@/features/opportunities/opportunity-schema'

/**
 * AC-070: create requires the identity fields; probability out of 0..100 is
 * rejected. Directive 2026-07-21: supervisor_id is nullable in BOTH create
 * and edit (it derives from the linked Lead's Operatore, which may be empty).
 */

function baseValues(overrides: Record<string, unknown> = {}) {
  return {
    registry_id: 1,
    // Spec 0043 D-3: opportunity_status_id is mandatory, mirrors registry_id.
    referent_id: null,
    commercial_id: null,
    reporter_id: null,
    supervisor_id: 9,
    source_id: null,
    // Spec 0056: facoltativa, never submit-blocking.
    operational_site_id: null,
    // Spec 0047: Regione, never submit-blocking.
    state_id: null,
    // product_lines is mandatory (>=1 row, user directive 2026-07-17): the base
    // happy-path carries one valid row; the empty-collection case overrides it.
    product_lines: [{ business_function_id: 1, product_category_id: 11 }],
    // products_of_interest is mandatory too (>=1 product, user directive
    // 2026-07-23): the base happy-path carries one; the empty case overrides it.
    products_of_interest: [7],
    rewards: [],
    manager_slots: [],
    start_date: null,
    expected_close_date: null,
    estimated_value: null,
    // A-6: rendered as a slider that always holds a value (default 0), never null.
    success_probability: 0,
    // "Note generali" (2026-07-27): free text, never submit-blocking.
    general_notes: null,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildCreateOpportunitySchema', () => {
  it('accepts a valid payload with all create-required fields set', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues())
    expect(result.success).toBe(true)
  })

  it('rejects a missing registry_id', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues({ registry_id: null }))
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'registry_id')).toBe(true)
    }
  })

  it('accepts every truly optional relation left null', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(
      baseValues({ referent_id: null, commercial_id: null, reporter_id: null, source_id: null }),
    )
    expect(result.success).toBe(true)
  })

  /** Directive 2026-07-21 (relaxes AC-070's supervisor requirement): a missing supervisor_id is accepted on create too. */
  it('accepts a missing supervisor_id', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues({ supervisor_id: null }))

    expect(result.success).toBe(true)
  })

  /**
   * Amendment rev.3 (AC-097/099/106): `product_lines` replaces the single
   * business_function_id/product_category_id fields with an inline-editable
   * row collection (mirrors `manager_slots`: "Add" appends an empty row).
   * Each id is individually nullable, but a `superRefine` requires BOTH
   * non-null per row before submit.
   */
  // User directive 2026-07-23: mandatory exactly like `product_lines`.
  describe('products_of_interest', () => {
    it('rejects an empty collection', () => {
      const schema = buildCreateOpportunitySchema(i18n.t)
      const result = schema.safeParse(baseValues({ products_of_interest: [] }))
      expect(result.success).toBe(false)
      if (!result.success) {
        expect(result.error.issues.some((issue) => issue.path.join('.') === 'products_of_interest')).toBe(true)
      }
    })

    it('rejects an empty collection on update too (never clearable)', () => {
      const schema = buildUpdateOpportunitySchema(i18n.t, [
        { business_function_id: 1, product_category_id: 11 },
      ])
      const result = schema.safeParse(baseValues({ products_of_interest: [] }))
      expect(result.success).toBe(false)
    })

    it('accepts one or more products', () => {
      const schema = buildCreateOpportunitySchema(i18n.t)
      expect(schema.safeParse(baseValues({ products_of_interest: [7, 9] })).success).toBe(true)
    })
  })

  describe('product_lines (amendment rev.3)', () => {
    it('rejects an empty collection (user directive 2026-07-17: at least one row required)', () => {
      const schema = buildCreateOpportunitySchema(i18n.t)
      const result = schema.safeParse(baseValues({ product_lines: [] }))
      expect(result.success).toBe(false)
      if (!result.success) {
        expect(result.error.issues.some((issue) => issue.path.join('.') === 'product_lines')).toBe(true)
      }
    })

    // Spec 0077 INV-2: every row shares the same Funzione aziendale — the
    // second row here reuses row 1's, unlike its "different roots" sibling
    // in the dedicated `INV-2` suite below.
    it('accepts one or more complete rows', () => {
      const schema = buildCreateOpportunitySchema(i18n.t)
      const result = schema.safeParse(
        baseValues({
          product_lines: [
            { business_function_id: 1, product_category_id: 11 },
            { business_function_id: 1, product_category_id: 22 },
          ],
        }),
      )
      expect(result.success).toBe(true)
    })

    it('rejects a freshly-added empty row (both ids null)', () => {
      const schema = buildCreateOpportunitySchema(i18n.t)
      const result = schema.safeParse(
        baseValues({ product_lines: [{ business_function_id: null, product_category_id: null }] }),
      )
      expect(result.success).toBe(false)
      if (!result.success) {
        expect(result.error.issues.some((issue) => issue.path.join('.') === 'product_lines')).toBe(true)
      }
    })

    it('rejects a row missing only business_function_id', () => {
      const schema = buildCreateOpportunitySchema(i18n.t)
      const result = schema.safeParse(
        baseValues({ product_lines: [{ business_function_id: null, product_category_id: 11 }] }),
      )
      expect(result.success).toBe(false)
    })

    it('rejects a row missing only product_category_id', () => {
      const schema = buildCreateOpportunitySchema(i18n.t)
      const result = schema.safeParse(
        baseValues({ product_lines: [{ business_function_id: 1, product_category_id: null }] }),
      )
      expect(result.success).toBe(false)
    })

    it('rejects the whole collection when only one of several rows is incomplete', () => {
      const schema = buildCreateOpportunitySchema(i18n.t)
      const result = schema.safeParse(
        baseValues({
          product_lines: [
            { business_function_id: 1, product_category_id: 11 },
            { business_function_id: 2, product_category_id: null },
          ],
        }),
      )
      expect(result.success).toBe(false)
    })
  })

  it('accepts every truly optional field when set', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(
      baseValues({
        referent_id: 6,
        commercial_id: 7,
        reporter_id: 8,
        supervisor_id: 9,
        source_id: 10,
        product_lines: [{ business_function_id: 5, product_category_id: 11 }],
        manager_slots: [9, null, 12],
        start_date: '2026-01-01',
        expected_close_date: '2026-06-30',
        estimated_value: 15000.5,
        success_probability: 60,
      }),
    )
    expect(result.success).toBe(true)
  })

  it('rejects success_probability above 100', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues({ success_probability: 101 }))
    expect(result.success).toBe(false)
  })

  it('rejects success_probability below 0', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues({ success_probability: -1 }))
    expect(result.success).toBe(false)
  })

  it('accepts success_probability at the 0 and 100 boundaries', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    expect(schema.safeParse(baseValues({ success_probability: 0 })).success).toBe(true)
    expect(schema.safeParse(baseValues({ success_probability: 100 })).success).toBe(true)
  })

  it('rejects a negative estimated_value', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues({ estimated_value: -1 }))
    expect(result.success).toBe(false)
  })

  // Spec 0080 amendment A1: the ceiling moved from 4 to 12 (MAX_MANAGERS, now
  // shared with `registry-schema.ts` via `MAX_MANAGER_SLOTS`).
  it(`AC-052: accepts exactly ${MAX_MANAGERS} filled manager slots`, () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const filled = Array.from({ length: MAX_MANAGERS }, (_, index) => index + 1)
    const result = schema.safeParse(baseValues({ manager_slots: filled }))
    expect(result.success).toBe(true)
  })

  it(`AC-052: rejects more than ${MAX_MANAGERS} filled manager slots`, () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const overflowing = Array.from({ length: MAX_MANAGERS + 1 }, (_, index) => index + 1)
    const result = schema.safeParse(baseValues({ manager_slots: overflowing }))
    expect(result.success).toBe(false)
  })
})

/** Spec 0056: facoltativa, never submit-blocking. */
describe('operational_site_id (spec 0056)', () => {
  it('accepts a null operational_site_id', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    expect(schema.safeParse(baseValues({ operational_site_id: null })).success).toBe(true)
  })

  it('accepts a set operational_site_id', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    expect(schema.safeParse(baseValues({ operational_site_id: 8 })).success).toBe(true)
  })
})

/** Spec 0047 (AC-026): the field is optional, non-blocking. */
describe('state_id (spec 0047)', () => {
  it('accepts it left null', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    expect(schema.safeParse(baseValues()).success).toBe(true)
  })

  it('accepts it set', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(baseValues({ state_id: 3 }))
    expect(result.success).toBe(true)
  })
})

describe('buildUpdateOpportunitySchema', () => {
  it('keeps supervisor_id nullable for existing opportunities', () => {
    const schema = buildUpdateOpportunitySchema(i18n.t, [
      { business_function_id: 1, product_category_id: 11 },
    ])

    expect(schema.safeParse(baseValues({ supervisor_id: null })).success).toBe(true)
  })
})

/**
 * Spec 0077 INV-2: every `product_lines` row shares the same Funzione
 * aziendale, in both management modes. D-5 grandfathering: on update the rule
 * only fires once the collection differs from what is persisted
 * (`originalProductLines`) — mirrors the work panel's own sparse gate
 * (`request-work-schema.ts`), and AC-016/017 apply here too.
 */
describe('product_lines — shared business function (spec 0077 INV-2)', () => {
  it('rejects two rows with different business functions on create', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(
      baseValues({
        product_lines: [
          { business_function_id: 1, product_category_id: 11 },
          { business_function_id: 2, product_category_id: 22 },
        ],
      }),
    )

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'product_lines')).toBe(true)
    }
  })

  it('accepts several rows sharing the same business function on create', () => {
    const schema = buildCreateOpportunitySchema(i18n.t)
    const result = schema.safeParse(
      baseValues({
        product_lines: [
          { business_function_id: 1, product_category_id: 11 },
          { business_function_id: 1, product_category_id: 22 },
        ],
      }),
    )

    expect(result.success).toBe(true)
  })

  it('rejects mismatched functions on update once the collection actually changed', () => {
    const schema = buildUpdateOpportunitySchema(i18n.t, [
      { business_function_id: 1, product_category_id: 11 },
    ])
    const result = schema.safeParse(
      baseValues({
        product_lines: [
          { business_function_id: 1, product_category_id: 11 },
          { business_function_id: 2, product_category_id: 22 },
        ],
      }),
    )

    expect(result.success).toBe(false)
  })

  /** AC-016 (opportunity form's own D-5 grandfathering): a legacy record whose rows never conformed stays saveable while `product_lines` is left untouched. */
  it('leaves a non-conformant historic collection alone while it stays untouched (D-5, AC-016)', () => {
    const historicRows = [
      { business_function_id: 1, product_category_id: 11 },
      { business_function_id: 2, product_category_id: 22 },
    ]
    const schema = buildUpdateOpportunitySchema(i18n.t, historicRows)

    // Same rows submitted back unchanged, only `general_notes` differs.
    const result = schema.safeParse(
      baseValues({ product_lines: historicRows, general_notes: 'Updated note.' }),
    )

    expect(result.success).toBe(true)
  })
})
