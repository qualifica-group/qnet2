import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildRequestCreateSchema } from '@/features/request-management/request-create-schema'

/**
 * Spec 0132: `product_lines` picks a ROOT category then one of its
 * descendants — `root_category_id` is UI-only state (D-3, never validated as
 * a domain field); only `product_category_id` must be non-null per row.
 */

function baseValues(overrides: Record<string, unknown> = {}) {
  return {
    registry_id: 1,
    product_lines: [{ root_category_id: null, product_category_id: 11 }],
    source_id: 30,
    reporter_id: null,
    supervisor_id: null,
    // Spec 0097 D-1: the team replaces the single "Operatore" field; empty
    // here, which the schema accepts (the server then applies its own default).
    manager_slots: [null, null, null, null],
    operational_site_id: null,
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

  it('accepts several rows under different root categories', () => {
    const schema = buildRequestCreateSchema(i18n.t)
    const result = schema.safeParse(
      baseValues({
        product_lines: [
          { root_category_id: null, product_category_id: 11 },
          { root_category_id: null, product_category_id: 22 },
        ],
      }),
    )

    expect(result.success).toBe(true)
  })
})
