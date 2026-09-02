import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/campaigns/campaign-form-payload'
import type { CampaignDetail } from '@/features/campaigns/types'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'

/**
 * Spec 0023: create shape and BR-2's exclusion of the 3 classification
 * fields when linked. Spec 0025 AC-010/AC-011: the create payload carries a
 * manual `code` only when set (never blank), the update payload never
 * carries it (immutable after create). Spec 0027 BR-5: the 4 geo fields
 * follow their OWN per-level lock instead of the BR-2 all-or-nothing group —
 * the previous assertions treating `state_id` as one of the 4 BR-2 fields
 * were REWRITTEN (D-3), not tampered with.
 */

function values(overrides: Partial<CampaignFormValues> = {}): CampaignFormValues {
  return {
    code: '',
    project_id: null,
    name: 'Spring push',
    description: null,
    partner_id: null,
    operational_site_id: null,
    pipeline_status_id: 1,
    product_lines: [{ business_function_id: 2, product_category_id: 4 }],
    country_id: 10,
    state_id: 3,
    province_id: null,
    city_id: null,
    geo_locked_levels: [],
    start_date: '',
    end_date: '',
    total_budget: null,
    target_lead: null,
    custom_fields: {},
    ...overrides,
  }
}

function original(overrides: Partial<CampaignDetail> = {}): CampaignDetail {
  return {
    id: 9,
    code: 'CMP-0009',
    project_id: null,
    project: null,
    name: 'Spring push',
    description: null,
    partner_id: null,
    partner: null,
    operational_site_id: null,
    operational_site: null,
    derived_from_project: false,
    pipeline_status_id: 1,
    pipeline_status: { id: 1, name: 'Active', color: 'blue' },
    country_id: 10,
    country: { id: 10, name: 'Italy' },
    state_id: 3,
    state: { id: 3, name: 'Lombardy' },
    province_id: null,
    province: null,
    city_id: null,
    city: null,
    geo_scope: 'state',
    geo_locked_levels: [],
    product_lines: [{ id: 1, business_function: { id: 2, name: 'Sales' }, product_category: { id: 4, name: 'Hardware' } }],
    start_date: null,
    end_date: null,
    total_budget: null,
    target_lead: null,
    created_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload — standalone (BR-2/BR-5)', () => {
  it('includes the 3 classification fields and every geo field when project_id is null', () => {
    const payload = buildCreatePayload(values())

    expect(payload).toEqual({
      name: 'Spring push',
      project_id: null,
      description: null,
      partner_id: null,
      operational_site_id: null,
      pipeline_status_id: 1,
      product_lines: [{ business_function_id: 2, product_category_id: 4 }],
      country_id: 10,
      state_id: 3,
      province_id: null,
      city_id: null,
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
})

describe('buildCreatePayload — linked (BR-2/AC-020/AC-042)', () => {
  it('omits pipeline_status_id and product_lines when project_id is set, even if the form still holds values', () => {
    const payload = buildCreatePayload(values({ project_id: 7 }))

    expect(payload).not.toHaveProperty('pipeline_status_id')
    expect(payload).not.toHaveProperty('product_lines')
    expect(payload.project_id).toBe(7)
  })
})

describe('buildCreatePayload — geo per-level lock (spec 0027 BR-5)', () => {
  it('omits only the levels the linked project locks, sending the rest', () => {
    const payload = buildCreatePayload(
      values({
        project_id: 7,
        pipeline_status_id: null,
        country_id: null,
        state_id: 3,
        province_id: 5,
        city_id: 9,
        geo_locked_levels: ['country'],
      }),
    )

    expect(payload).not.toHaveProperty('country_id')
    expect(payload.state_id).toBe(3)
    expect(payload.province_id).toBe(5)
    expect(payload.city_id).toBe(9)
  })

  it('omits every geo field when the project fills all four levels', () => {
    const payload = buildCreatePayload(
      values({
        project_id: 7,
        pipeline_status_id: null,
        geo_locked_levels: ['country', 'state', 'province', 'city'],
      }),
    )

    expect(payload).not.toHaveProperty('country_id')
    expect(payload).not.toHaveProperty('state_id')
    expect(payload).not.toHaveProperty('province_id')
    expect(payload).not.toHaveProperty('city_id')
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(values(), original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload(values({ name: 'Renamed' }), original())).toEqual({ name: 'Renamed' })
  })

  it('never includes a code field, even when the form value differs from the original (AC-011)', () => {
    const payload = buildUpdatePayload(values({ name: 'Renamed', code: 'SOMETHING-ELSE' }), original())
    expect(payload).not.toHaveProperty('code')
  })

  it('includes pipeline_status_id and product_lines on a linked→standalone transition, regardless of diff against the effective original', () => {
    // The campaign WAS linked (its own DB columns were NULL; `original` exposes
    // the project's effective values) and the user now unlinks it, keeping the
    // exact same ids the project had — a naive diff would wrongly omit them.
    const linkedOriginal = original({
      project_id: 5,
      derived_from_project: true,
      pipeline_status_id: 1,
      product_lines: [{ id: 1, business_function: { id: 2, name: 'Sales' }, product_category: { id: 4, name: 'Hardware' } }],
    })
    const payload = buildUpdatePayload(
      values({
        project_id: null,
        pipeline_status_id: 1,
        product_lines: [{ business_function_id: 2, product_category_id: 4 }],
      }),
      linkedOriginal,
    )

    expect(payload).toEqual({
      project_id: null,
      pipeline_status_id: 1,
      product_lines: [{ business_function_id: 2, product_category_id: 4 }],
    })
  })

  it('omits pipeline_status_id and product_lines when the campaign stays linked, even if the diffed values differ', () => {
    const linkedOriginal = original({
      project_id: 5,
      derived_from_project: true,
      pipeline_status_id: 9,
      product_lines: [{ id: 1, business_function: { id: 9, name: 'Other' }, product_category: { id: 9, name: 'Other' } }],
    })
    const payload = buildUpdatePayload(values({ project_id: 5 }), linkedOriginal)

    expect(payload).not.toHaveProperty('pipeline_status_id')
    expect(payload).not.toHaveProperty('product_lines')
  })

  it('diffs pipeline_status_id normally when the campaign stays standalone', () => {
    const payload = buildUpdatePayload(values({ pipeline_status_id: 8 }), original())
    expect(payload).toEqual({ pipeline_status_id: 8 })
  })

  it('omits product_lines from the update diff when the set is unchanged, regardless of row order', () => {
    const payload = buildUpdatePayload(
      values({
        product_lines: [
          { business_function_id: 7, product_category_id: 8 },
          { business_function_id: 2, product_category_id: 4 },
        ],
      }),
      original({
        product_lines: [
          { id: 1, business_function: { id: 2, name: 'Sales' }, product_category: { id: 4, name: 'Hardware' } },
          { id: 2, business_function: { id: 7, name: 'Support' }, product_category: { id: 8, name: 'Gadgets' } },
        ],
      }),
    )
    expect(payload).not.toHaveProperty('product_lines')
  })

  it('includes product_lines in the update diff when the set changed (standalone)', () => {
    const payload = buildUpdatePayload(
      values({ product_lines: [{ business_function_id: 9, product_category_id: 10 }] }),
      original(),
    )
    expect(payload.product_lines).toEqual([{ business_function_id: 9, product_category_id: 10 }])
  })
})

describe('buildUpdatePayload — geo per-level diff (spec 0027 BR-5)', () => {
  it('omits a level that stays locked', () => {
    const linkedOriginal = original({
      project_id: 7,
      country_id: 10,
      country: { id: 10, name: 'Italy' },
      geo_locked_levels: ['country'],
    })
    const payload = buildUpdatePayload(
      values({ project_id: 7, country_id: 10, geo_locked_levels: ['country'] }),
      linkedOriginal,
    )
    expect(payload).not.toHaveProperty('country_id')
  })

  it('sends a level that WAS locked but is unlocked now, even if its value did not change', () => {
    const linkedOriginal = original({
      project_id: 7,
      country_id: 10,
      country: { id: 10, name: 'Italy' },
      geo_locked_levels: ['country'],
    })
    const payload = buildUpdatePayload(
      values({ project_id: 7, country_id: 10, geo_locked_levels: [] }),
      linkedOriginal,
    )
    expect(payload.country_id).toBe(10)
  })

  it('diffs an always-unlocked level normally', () => {
    const payload = buildUpdatePayload(values({ province_id: 5 }), original())
    expect(payload).toEqual({ province_id: 5 })
  })

  it('includes the changed operational_site_id (always-own field, like partner_id)', () => {
    const payload = buildUpdatePayload(
      values({ operational_site_id: 81 }),
      original({ operational_site_id: null }),
    )
    expect(payload).toEqual({ operational_site_id: 81 })
  })

  it('omits operational_site_id when unchanged', () => {
    const payload = buildUpdatePayload(
      values({ operational_site_id: 81 }),
      original({ operational_site_id: 81 }),
    )
    expect(payload).toEqual({})
  })

  it('unlinking the project sends every previously-locked level, regardless of value equality', () => {
    const linkedOriginal = original({
      project_id: 7,
      country_id: 10,
      country: { id: 10, name: 'Italy' },
      state_id: 3,
      state: { id: 3, name: 'Lombardy' },
      geo_locked_levels: ['country', 'state'],
    })
    const payload = buildUpdatePayload(
      values({ project_id: null, country_id: 10, state_id: 3, geo_locked_levels: [] }),
      linkedOriginal,
    )
    expect(payload.country_id).toBe(10)
    expect(payload.state_id).toBe(3)
  })
})
