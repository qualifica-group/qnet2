import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildRequestWorkSchema,
  type RequestWorkOriginalState,
} from '@/features/request-management/request-work-schema'
import type { ApplicableAttribute } from '@/features/request-management/types'

/**
 * The panel's loaded state: what the schema compares against to decide
 * whether a key is going to be sent at all (the endpoint is sparse).
 */
function original(overrides: Partial<RequestWorkOriginalState> = {}): RequestWorkOriginalState {
  return {
    attribute_values: {},
    products_of_interest: [7],
    product_lines: [{ business_function_id: 40, product_category_id: 500 }],
    client_identity: null,
    client_contacts: [],
    client_address: null,
    ...overrides,
  }
}

function values(overrides: Record<string, unknown> = {}) {
  return {
    next_callback_at: null,
    client_identity: null,
    client_contacts: [],
    client_address: [],
    // Mandatory since the user directive 2026-07-23 (>=1 product).
    products_of_interest: [7],
    // Editable since the user directive 2026-07-31; unchanged here, so the
    // collection's own rules stay dormant (see the dedicated suite below).
    product_lines: [{ business_function_id: 40, product_category_id: 500 }],
    rewards: [],
    // Mandatory since the user directive 2026-07-29 (see the dedicated suite below).
    source_id: 30,
    reporter_id: null,
    operator_id: null,
    operational_site_id: null,
    attribute_values: {},
    ...overrides,
  }
}

// User directive 2026-07-23: the panel writes the same collection as the
// opportunities form, so it carries the same mandatory rule.
describe('buildRequestWorkSchema — products of interest', () => {
  it('rejects an empty collection', () => {
    const schema = buildRequestWorkSchema([], original(), i18n.t)
    const result = schema.safeParse(values({ products_of_interest: [] }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'products_of_interest')).toBe(true)
    }
  })

  it('accepts one or more products', () => {
    const schema = buildRequestWorkSchema([], original(), i18n.t)

    expect(schema.safeParse(values({ products_of_interest: [7] })).success).toBe(true)
  })

  /**
   * `UpdateRequestRequest` marks the key `sometimes`: an untouched collection
   * is never sent, so it is never validated server-side either. Blocking the
   * submit for it would leave a record that legitimately has none unsavable
   * for every OTHER field — the panel would refuse the save with no request
   * ever going out.
   */
  it('leaves an empty collection alone while it stays untouched', () => {
    const schema = buildRequestWorkSchema([], original({ products_of_interest: [] }), i18n.t)

    expect(schema.safeParse(values({ products_of_interest: [] })).success).toBe(true)
  })
})

// User directive 2026-07-31: funzione aziendale + categoria prodotto are
// edited from the panel too, under the create form's own two rules — but only
// once the collection is actually touched (same sparse gate as above).
describe('buildRequestWorkSchema — product lines', () => {
  const EDITED = [{ business_function_id: 41, product_category_id: 501 }]

  it('rejects clearing the collection', () => {
    const schema = buildRequestWorkSchema([], original(), i18n.t)
    const result = schema.safeParse(values({ product_lines: [] }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'product_lines')).toBe(true)
    }
  })

  it('rejects a row missing its product category', () => {
    const schema = buildRequestWorkSchema([], original(), i18n.t)
    const result = schema.safeParse(
      values({ product_lines: [{ business_function_id: 41, product_category_id: null }] }),
    )

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'product_lines.0')).toBe(true)
    }
  })

  it('accepts a complete replacement', () => {
    const schema = buildRequestWorkSchema([], original(), i18n.t)

    expect(schema.safeParse(values({ product_lines: EDITED })).success).toBe(true)
  })

  it('leaves an untouched collection alone, even when the request has no line', () => {
    const schema = buildRequestWorkSchema([], original({ product_lines: [] }), i18n.t)

    expect(schema.safeParse(values({ product_lines: [] })).success).toBe(true)
  })

  /**
   * Spec 0077 INV-2: every row shares the same Funzione aziendale, in both
   * management modes — gated by the SAME sparse rule as the two suites
   * above (AC-043: identical behaviour to `request-create-schema.ts` and
   * `opportunity-schema.ts`).
   */
  describe('shared business function (spec 0077 INV-2)', () => {
    it('rejects mismatched functions once the collection is edited', () => {
      const schema = buildRequestWorkSchema([], original(), i18n.t)
      const result = schema.safeParse(
        values({
          product_lines: [
            { business_function_id: 41, product_category_id: 501 },
            { business_function_id: 42, product_category_id: 502 },
          ],
        }),
      )

      expect(result.success).toBe(false)
      if (!result.success) {
        expect(result.error.issues.some((issue) => issue.path.join('.') === 'product_lines')).toBe(true)
      }
    })

    it('accepts several edited rows sharing the same business function', () => {
      const schema = buildRequestWorkSchema([], original(), i18n.t)
      const result = schema.safeParse(
        values({
          product_lines: [
            { business_function_id: 41, product_category_id: 501 },
            { business_function_id: 41, product_category_id: 502 },
          ],
        }),
      )

      expect(result.success).toBe(true)
    })

    /** AC-044: a historic record whose rows never conformed stays saveable while `product_lines` is left untouched. */
    it('leaves a non-conformant historic collection alone while it stays untouched (D-5, AC-044)', () => {
      const historicRows = [
        { business_function_id: 41, product_category_id: 501 },
        { business_function_id: 42, product_category_id: 502 },
      ]
      const schema = buildRequestWorkSchema([], original({ product_lines: historicRows }), i18n.t)

      // The panel resubmits the SAME historic rows unchanged, only `next_callback_at` differs.
      const result = schema.safeParse(
        values({ product_lines: historicRows, next_callback_at: '2026-08-10T09:00:00Z' }),
      )

      expect(result.success).toBe(true)
    })
  })
})

/**
 * User directive 2026-07-29: the Fonte is mandatory, and DELIBERATELY not
 * gated on the key travelling the way the two rules above are — a request
 * without a source may not be saved at all, legacy rows included. The blocking
 * field is named in the panel's own save summary (`describeInvalidFields`), so
 * this refusal is visible rather than a silent dead button.
 */
describe('buildRequestWorkSchema — source (Fonte)', () => {
  it('rejects a null source', () => {
    const schema = buildRequestWorkSchema([], original(), i18n.t)
    const result = schema.safeParse(values({ source_id: null }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'source_id')).toBe(true)
    }
  })

  it('accepts a chosen source', () => {
    const schema = buildRequestWorkSchema([], original(), i18n.t)

    expect(schema.safeParse(values({ source_id: 30 })).success).toBe(true)
  })
})

/**
 * Same sparse mirror for the dynamic Attributes: `AttributeValueValidator`
 * checks `is_required` only on SUBMITTED codes, and the panel sends the map
 * only when one of them changed.
 */
describe('buildRequestWorkSchema — required attributes', () => {
  const ATTRIBUTES: ApplicableAttribute[] = [
    {
      id: 1,
      code: 'notes',
      name: 'Notes',
      type: 'text',
      description: null,
      help_text: null,
      placeholder: null,
      icon: null,
      config: null,
      relation_target: null,
      is_required: true,
      sort_order: 1,
      options: [],
    },
  ]

  it('leaves an empty required attribute alone while the map stays untouched', () => {
    const schema = buildRequestWorkSchema(ATTRIBUTES, original({ attribute_values: { notes: null } }), i18n.t)

    expect(schema.safeParse(values({ attribute_values: { notes: null } })).success).toBe(true)
  })

  it('rejects an empty required attribute once the map is edited', () => {
    const schema = buildRequestWorkSchema(
      ATTRIBUTES,
      original({ attribute_values: { notes: 'Some notes' } }),
      i18n.t,
    )
    const result = schema.safeParse(values({ attribute_values: { notes: '' } }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'attribute_values.notes')).toBe(true)
    }
  })
})
