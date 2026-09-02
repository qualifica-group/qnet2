import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCreateProjectSchema } from '@/features/projects/project-schema'
import { buildCustomFieldsSchema } from '@/features/custom-fields/build-custom-fields-schema'

/**
 * Spec 0023 D-5 (required status) and BR-6 (end_date >= start_date), mirrored
 * client-side. Spec 0025 AC-010: `code` is optional and, when set, mirrors
 * the backend's max:32.
 */

const EMPTY_CUSTOM_FIELDS_SCHEMA = buildCustomFieldsSchema(
  [],
  { resource: { view: true, create: true, update: true, delete: true, export: true, import: true }, fields: {}, actions: {} },
  i18n.t,
)

function baseValues() {
  return {
    code: 'PRJ-0001',
    name: 'New project',
    description: null,
    pipeline_status_id: 1,
    country_id: 1,
    state_id: null,
    province_id: null,
    city_id: null,
    product_lines: [{ business_function_id: 5, product_category_id: 6 }],
    partner_id: null,
    operational_site_id: null,
    start_date: '2026-01-01',
    end_date: '2026-12-31',
    total_budget: null,
    target_lead: null,
    custom_fields: {},
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildCreateProjectSchema', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse(baseValues())
    expect(result.success).toBe(true)
  })

  it('rejects a missing name', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({ ...baseValues(), name: '' })
    expect(result.success).toBe(false)
  })

  it('accepts a null pipeline_status_id (spec 0039 D-3: server falls back to "Nuovo")', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({ ...baseValues(), pipeline_status_id: null })
    expect(result.success).toBe(true)
  })

  it('rejects end_date earlier than start_date (BR-6)', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({
      ...baseValues(),
      start_date: '2026-02-01',
      end_date: '2026-01-01',
    })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'end_date')).toBe(true)
    }
  })

  it('accepts end_date equal to start_date', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({
      ...baseValues(),
      start_date: '2026-02-01',
      end_date: '2026-02-01',
    })
    expect(result.success).toBe(true)
  })

  it('rejects a missing start_date (now required)', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({ ...baseValues(), start_date: '' })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'start_date')).toBe(true)
    }
  })

  it('accepts a missing end_date (optional)', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({ ...baseValues(), end_date: '' })
    expect(result.success).toBe(true)
  })

})

describe('buildCreateProjectSchema — product_lines (spec 0094)', () => {
  it('rejects an empty product_lines collection (AC-045)', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({ ...baseValues(), product_lines: [] })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'product_lines')).toBe(true)
    }
  })

  it('rejects a row missing its business_function_id', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({
      ...baseValues(),
      product_lines: [{ business_function_id: null, product_category_id: 6 }],
    })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'product_lines')).toBe(true)
    }
  })

  it('rejects a row missing its product_category_id', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({
      ...baseValues(),
      product_lines: [{ business_function_id: 5, product_category_id: null }],
    })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'product_lines')).toBe(true)
    }
  })

  it('accepts multiple complete rows', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({
      ...baseValues(),
      product_lines: [
        { business_function_id: 5, product_category_id: 6 },
        { business_function_id: 7, product_category_id: 8 },
      ],
    })
    expect(result.success).toBe(true)
  })
})

describe('buildCreateProjectSchema — manual code (spec 0025 AC-010)', () => {
  it('rejects an empty code (now required, auto-filled by the form)', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({ ...baseValues(), code: '' })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'code')).toBe(true)
    }
  })

  it('accepts a valid manual code and trims it', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({ ...baseValues(), code: '  ACME-2026  ' })
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.code).toBe('ACME-2026')
    }
  })

  it('rejects a code of 33+ characters (mirrors backend max:32, AC-005)', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({ ...baseValues(), code: 'A'.repeat(33) })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'code')).toBe(true)
    }
  })
})

describe('buildCreateProjectSchema — geo hierarchy (spec 0027 BR-4)', () => {
  it('rejects a null country_id', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({ ...baseValues(), country_id: null })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'country_id')).toBe(true)
    }
  })

  it('accepts a full country -> state -> province -> city tuple', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({
      ...baseValues(),
      state_id: 10,
      province_id: 50,
      city_id: 100,
    })
    expect(result.success).toBe(true)
  })

  it('rejects a province set without a state', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({ ...baseValues(), province_id: 50 })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'province_id')).toBe(true)
    }
  })

  it('rejects a city set without a state', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({ ...baseValues(), city_id: 100 })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'city_id')).toBe(true)
    }
  })

  it('accepts a city set alongside a state but no province (province is optional)', () => {
    const schema = buildCreateProjectSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA)
    const result = schema.safeParse({ ...baseValues(), state_id: 10, city_id: 100 })
    expect(result.success).toBe(true)
  })
})
