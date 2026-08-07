import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildRequestCreateSchema } from '@/features/request-management/request-create-schema'

/**
 * Spec 0077 INV-2: every `product_lines` row of a request shares the same
 * Funzione aziendale, in both management modes — the create channel has no
 * persisted record to grandfather (D-5 only gates the work panel's edits,
 * see `request-work-schema.test.ts`), so the rule is unconditional here.
 */

function baseValues(overrides: Record<string, unknown> = {}) {
  return {
    registry_id: 1,
    product_lines: [{ business_function_id: 1, product_category_id: 11 }],
    source_id: 30,
    reporter_id: null,
    operator_id: null,
    operational_site_id: null,
    products_of_interest: [],
    // "Linee dell'offerta" (user directive 2026-08-07): part of the schema's
    // shape, empty here — a request often starts with no offer row at all.
    offer_lines: [],
    rewards: [],
    next_callback_at: null,
    general_notes: '',
    // "Informazioni aggiuntive" (user directive 2026-08-07): part of the
    // schema's shape, empty here — with no applicable attribute the block
    // validates nothing (its own rules live in the suite below).
    attribute_values: {},
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildRequestCreateSchema — product lines', () => {
  it('accepts a single complete row', () => {
    const schema = buildRequestCreateSchema(i18n.t)
    expect(schema.safeParse(baseValues()).success).toBe(true)
  })

  it('rejects an empty collection', () => {
    const schema = buildRequestCreateSchema(i18n.t)
    const result = schema.safeParse(baseValues({ product_lines: [] }))

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'product_lines')).toBe(true)
    }
  })

  describe('shared business function (spec 0077 INV-2)', () => {
    it('rejects two rows with different business functions', () => {
      const schema = buildRequestCreateSchema(i18n.t)
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

    it('accepts several rows sharing the same business function', () => {
      const schema = buildRequestCreateSchema(i18n.t)
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
  })
})
