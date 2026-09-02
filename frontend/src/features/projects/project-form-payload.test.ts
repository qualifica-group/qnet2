import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/projects/project-form-payload'
import type { ProjectDetail } from '@/features/projects/types'
import type { ProjectFormValues } from '@/features/projects/use-project-form'

/**
 * Spec 0023: create shape and the update sparse diff. Spec 0025 AC-010/AC-011:
 * the create payload carries a manual `code` only when set (never blank), the
 * update payload never carries it (immutable after create).
 */

function values(overrides: Partial<ProjectFormValues> = {}): ProjectFormValues {
  return {
    code: '',
    name: 'Acme rollout',
    description: null,
    pipeline_status_id: 3,
    country_id: 1,
    state_id: null,
    province_id: null,
    city_id: null,
    product_lines: [{ business_function_id: 5, product_category_id: 6 }],
    partner_id: null,
    operational_site_id: null,
    start_date: '',
    end_date: '',
    total_budget: null,
    target_lead: null,
    custom_fields: {},
    ...overrides,
  }
}

function original(overrides: Partial<ProjectDetail> = {}): ProjectDetail {
  return {
    id: 4,
    code: 'PRJ-0004',
    name: 'Acme rollout',
    description: null,
    pipeline_status_id: 3,
    pipeline_status: { id: 3, name: 'Active', color: 'blue' },
    country_id: 1,
    country: { id: 1, name: 'Italy' },
    state_id: null,
    state: null,
    province_id: null,
    province: null,
    city_id: null,
    city: null,
    geo_scope: 'country',
    product_lines: [{ id: 1, business_function: { id: 5, name: 'Sales' }, product_category: { id: 6, name: 'Widgets' } }],
    partner_id: null,
    partner: null,
    operational_site_id: null,
    operational_site: null,
    start_date: null,
    end_date: null,
    total_budget: null,
    target_lead: null,
    allocated_budget: '0.00',
    remaining_budget: null,
    campaigns_count: 0,
    created_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('builds the full create payload shape without a code field when unset (AC-010)', () => {
    const payload = buildCreatePayload(values())

    expect(payload).toEqual({
      name: 'Acme rollout',
      pipeline_status_id: 3,
      description: null,
      product_lines: [{ business_function_id: 5, product_category_id: 6 }],
      country_id: 1,
      state_id: null,
      province_id: null,
      city_id: null,
      partner_id: null,
      operational_site_id: null,
      start_date: null,
      end_date: null,
      total_budget: null,
      target_lead: null,
    })
    expect(payload).not.toHaveProperty('code')
  })

  it('includes a trimmed manual code when set (AC-010)', () => {
    const payload = buildCreatePayload(values({ code: '  ACME-2026  ' }))
    expect(payload.code).toBe('ACME-2026')
  })

  it('omits code when it is blank after trimming (empty equals absent, AC-003)', () => {
    const payload = buildCreatePayload(values({ code: '   ' }))
    expect(payload).not.toHaveProperty('code')
  })

  it('maps empty date strings to null and keeps numeric fields', () => {
    const payload = buildCreatePayload(
      values({ start_date: '2026-01-01', end_date: '2026-03-01', total_budget: 1000, target_lead: 25 }),
    )

    expect(payload.start_date).toBe('2026-01-01')
    expect(payload.end_date).toBe('2026-03-01')
    expect(payload.total_budget).toBe(1000)
    expect(payload.target_lead).toBe(25)
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(values(), original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload(values({ name: 'Renamed' }), original())).toEqual({ name: 'Renamed' })
  })

  it('includes only the changed pipeline_status_id', () => {
    expect(buildUpdatePayload(values({ pipeline_status_id: 9 }), original())).toEqual({
      pipeline_status_id: 9,
    })
  })

  it('includes the changed total_budget, diffed against the string-typed original', () => {
    const payload = buildUpdatePayload(
      values({ total_budget: 500 }),
      original({ total_budget: '1000.00' }),
    )
    expect(payload).toEqual({ total_budget: 500 })
  })

  it('omits total_budget when it numerically matches the string-typed original', () => {
    const payload = buildUpdatePayload(
      values({ total_budget: 1000 }),
      original({ total_budget: '1000.00' }),
    )
    expect(payload).toEqual({})
  })

  it('maps a cleared date back to null in the diff', () => {
    const payload = buildUpdatePayload(values({ start_date: '' }), original({ start_date: '2026-01-01' }))
    expect(payload).toEqual({ start_date: null })
  })

  it('never includes a code field, even when the form value differs from the original (AC-011)', () => {
    const payload = buildUpdatePayload(values({ name: 'Renamed', code: 'SOMETHING-ELSE' }), original())
    expect(payload).not.toHaveProperty('code')
  })

  it('includes only the changed geo levels (spec 0027 BR-4)', () => {
    const payload = buildUpdatePayload(
      values({ state_id: 10, city_id: 100 }),
      original({ state_id: null, city_id: null }),
    )
    expect(payload).toEqual({ state_id: 10, city_id: 100 })
  })

  it('includes the changed operational_site_id', () => {
    const payload = buildUpdatePayload(
      values({ operational_site_id: 8 }),
      original({ operational_site_id: null }),
    )
    expect(payload).toEqual({ operational_site_id: 8 })
  })

  it('omits operational_site_id when unchanged', () => {
    const payload = buildUpdatePayload(
      values({ operational_site_id: 8 }),
      original({ operational_site_id: 8 }),
    )
    expect(payload).toEqual({})
  })
})

/** Spec 0094 (AC-045): the row editor's full-replace collection, diffed as an unordered set. */
describe('product_lines (spec 0094)', () => {
  it('filters out an incomplete row before sending it (create)', () => {
    const payload = buildCreatePayload(
      values({
        product_lines: [
          { business_function_id: 5, product_category_id: 6 },
          { business_function_id: 7, product_category_id: null },
        ],
      }),
    )
    expect(payload.product_lines).toEqual([{ business_function_id: 5, product_category_id: 6 }])
  })

  it('omits product_lines from the update diff when the set is unchanged, regardless of row order', () => {
    const payload = buildUpdatePayload(
      values({
        product_lines: [
          { business_function_id: 7, product_category_id: 8 },
          { business_function_id: 5, product_category_id: 6 },
        ],
      }),
      original({
        product_lines: [
          { id: 1, business_function: { id: 5, name: 'Sales' }, product_category: { id: 6, name: 'Widgets' } },
          { id: 2, business_function: { id: 7, name: 'Support' }, product_category: { id: 8, name: 'Gadgets' } },
        ],
      }),
    )
    expect(payload).not.toHaveProperty('product_lines')
  })

  it('includes product_lines in the update diff when the set changed', () => {
    const payload = buildUpdatePayload(
      values({ product_lines: [{ business_function_id: 9, product_category_id: 10 }] }),
      original(),
    )
    expect(payload.product_lines).toEqual([{ business_function_id: 9, product_category_id: 10 }])
  })
})
