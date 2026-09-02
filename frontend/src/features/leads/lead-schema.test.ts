import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCreateLeadSchema } from '@/features/leads/lead-schema'

/**
 * AC-060: contact and campaign missing produce a localized validation error
 * (BR-1, D-1); the other fields empty pass.
 */

function baseValues(overrides: Record<string, unknown> = {}) {
  return {
    registry_id: 1,
    campaign_id: 2,
    operational_site_id: null,
    source_id: null,
    operator_id: null,
    state_id: null,
    notes: null,
    extra_fields: [],
    products_of_interest: [],
    convert_to_opportunity: false,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildCreateLeadSchema', () => {
  it('accepts a valid payload with only the required contact and campaign set', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(baseValues())
    expect(result.success).toBe(true)
  })

  it('rejects a missing registry_id (contact)', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(baseValues({ registry_id: null }))
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'registry_id')).toBe(true)
    }
  })

  it('rejects a missing campaign_id', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(baseValues({ campaign_id: null }))
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'campaign_id')).toBe(true)
    }
  })

  it('accepts the 5 optional fields left null', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(
      baseValues({
        operational_site_id: null,
        source_id: null,
        operator_id: null,
        state_id: null,
        notes: null,
      }),
    )
    expect(result.success).toBe(true)
  })

  it('accepts the 5 optional fields when set', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(
      baseValues({
        operational_site_id: 3,
        source_id: 4,
        operator_id: 5,
        state_id: 6,
        notes: 'Some note',
      }),
    )
    expect(result.success).toBe(true)
  })

  it('rejects notes beyond the 5000-character limit', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(baseValues({ notes: 'a'.repeat(5001) }))
    expect(result.success).toBe(false)
  })
})

/** Spec 0094, D-5: a lead with no products of interest stays valid (AC-036). */
describe('buildCreateLeadSchema — products_of_interest', () => {
  it('accepts an empty products_of_interest array', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(baseValues({ products_of_interest: [] }))
    expect(result.success).toBe(true)
  })

  it('accepts a non-empty products_of_interest array', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(baseValues({ products_of_interest: [7, 9] }))
    expect(result.success).toBe(true)
  })
})

/** AC-014: extra_fields free-form key/value pairs (spec 0033). */
describe('buildCreateLeadSchema — extra_fields', () => {
  it('accepts an empty extra_fields array', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(baseValues({ extra_fields: [] }))
    expect(result.success).toBe(true)
  })

  it('accepts valid key/value pairs', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(
      baseValues({
        extra_fields: [
          { key: 'Original column A', value: 'foo' },
          { key: 'Original column B', value: '' },
        ],
      }),
    )
    expect(result.success).toBe(true)
  })

  it('rejects a row with an empty (or whitespace-only) key', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(baseValues({ extra_fields: [{ key: '   ', value: 'foo' }] }))
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'extra_fields.0.key')).toBe(
        true,
      )
    }
  })

  it('rejects duplicate keys (case-insensitive)', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(
      baseValues({
        extra_fields: [
          { key: 'Source', value: 'a' },
          { key: 'source', value: 'b' },
        ],
      }),
    )
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'extra_fields.1.key')).toBe(
        true,
      )
    }
  })
})

/**
 * Directive 2026-07-21 (relaxes spec 0044 AC-041): Operator and Site stay
 * optional regardless of `convert_to_opportunity` — the derived Opportunity
 * simply inherits a null supervisor.
 */
describe('buildCreateLeadSchema — convert_to_opportunity', () => {
  it('accepts operator_id/operational_site_id left null when the flag is off', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(baseValues({ convert_to_opportunity: false }))
    expect(result.success).toBe(true)
  })

  it('accepts a missing operator_id when the flag is on', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(
      baseValues({ convert_to_opportunity: true, operational_site_id: 3, operator_id: null }),
    )
    expect(result.success).toBe(true)
  })

  it('accepts a missing operational_site_id when the flag is on', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(
      baseValues({ convert_to_opportunity: true, operator_id: 5, operational_site_id: null }),
    )
    expect(result.success).toBe(true)
  })

  it('accepts the flag on when both operator_id and operational_site_id are set', () => {
    const schema = buildCreateLeadSchema(i18n.t)
    const result = schema.safeParse(
      baseValues({ convert_to_opportunity: true, operator_id: 5, operational_site_id: 3 }),
    )
    expect(result.success).toBe(true)
  })
})
