import { describe, expect, it } from 'vitest'
import { buildCreatePayload } from '@/features/opportunities/opportunity-form-payload'
import { createValues } from '@/features/opportunities/opportunity-form-payload-fixtures'

/**
 * buildCreatePayload: the POST shape (spec 0040 MT-6/A-1). The PATCH sparse
 * diff lives in opportunity-form-payload-update.test.ts, split off when this
 * file crossed the 500-line hard limit (engineering.md §6); both build their
 * cases from opportunity-form-payload-fixtures.ts.
 */

describe('buildCreatePayload', () => {
  it('includes the required supervisor and every truly optional field as null when unset (AC-082)', () => {
    const payload = buildCreatePayload(createValues())

    expect(payload).toEqual({
      registry_id: 1,
      opportunity_status_id: 5,
      referent_id: null,
      commercial_id: null,
      reporter_id: null,
      supervisor_id: 9,
      source_id: null,
      operational_site_id: null,
      state_id: null,
      product_lines: [],
      products_of_interest: [],
      manager_slots: [],
      start_date: null,
      expected_close_date: null,
      estimated_value: null,
      success_probability: 0,
      general_notes: null,
    })
  })

  /** Spec 0056: facoltativa, never lead-derived — always sent as-is, even from a lead. */
  it('sends operational_site_id unconditionally, even when creating from a lead', () => {
    const payload = buildCreatePayload(
      createValues({ operational_site_id: 8 }),
      { leadId: 9, lockedFields: ['registry_id', 'source_id'] },
    )

    expect(payload.operational_site_id).toBe(8)
  })

  /** Spec 0047 (D1): never BR-2-locked — always sent as-is, even from a lead. */
  it('sends state_id unconditionally, even when creating from a lead', () => {
    const payload = buildCreatePayload(
      createValues({ state_id: 7 }),
      { leadId: 9, lockedFields: ['registry_id', 'source_id'] },
    )

    expect(payload.state_id).toBe(7)
  })

  /** Amendment rev.3 (AC-099/107): `product_lines` is always sent in full, never locked, even from a lead. */
  it('always sends product_lines in full, unlocked even when creating from a lead', () => {
    const payload = buildCreatePayload(
      createValues({
        product_lines: [
          { business_function_id: 40, product_category_id: 50 },
          { business_function_id: 41, product_category_id: 51 },
        ],
      }),
      { leadId: 9, lockedFields: ['registry_id'] },
    )

    expect(payload.product_lines).toEqual([
      { business_function_id: 40, product_category_id: 50 },
      { business_function_id: 41, product_category_id: 51 },
    ])
  })

  it('includes every set relation/estimate', () => {
    const payload = buildCreatePayload(
      createValues({
        referent_id: 10,
        manager_slots: [20, null, 21],
        start_date: '2026-01-01',
        estimated_value: 5000,
        success_probability: 40,
      }),
    )

    expect(payload.referent_id).toBe(10)
    expect(payload.manager_slots).toEqual([20, null, 21])
    expect(payload.start_date).toBe('2026-01-01')
    expect(payload.estimated_value).toBe(5000)
    expect(payload.success_probability).toBe(40)
  })

  /** Spec 0059 D-3: nothing to sync away on create, so an empty set is a no-op like an omitted key. */
  describe('rewards (spec 0059 D-3)', () => {
    it('omits rewards when nothing was assigned', () => {
      const payload = buildCreatePayload(createValues())
      expect(payload).not.toHaveProperty('rewards')
    })

    it('sends the assigned reward types in full', () => {
      const payload = buildCreatePayload(
        createValues({ rewards: [{ reward_type_id: 3 }, { reward_type_id: 7 }] }),
      )
      expect(payload.rewards).toEqual([{ reward_type_id: 3 }, { reward_type_id: 7 }])
    })
  })

  /** AC-075: creating from a Lead appends `lead_id` and OMITS every locked field entirely (BR-1/BR-2), never merely repeats it. */
  describe('create-from-lead (BR-1/BR-2, AC-075)', () => {
    it('appends lead_id and omits every locked field from the payload', () => {
      const payload = buildCreatePayload(
        createValues({
          registry_id: 30,
          referent_id: 10,
          source_id: 20,
        }),
        {
          leadId: 9,
          lockedFields: ['registry_id', 'referent_id', 'source_id'],
        },
      )

      expect(payload.lead_id).toBe(9)
      expect(payload).not.toHaveProperty('registry_id')
      expect(payload).not.toHaveProperty('referent_id')
      expect(payload).not.toHaveProperty('source_id')
    })

    it('sends a field whose derivation is null (BR-2: not locked, stays free) even from a lead', () => {
      const payload = buildCreatePayload(
        createValues({ source_id: 70 }),
        { leadId: 9, lockedFields: ['registry_id', 'referent_id'] },
      )

      expect(payload.source_id).toBe(70)
      expect(payload).not.toHaveProperty('registry_id')
      expect(payload).not.toHaveProperty('referent_id')
    })

    it('never appends lead_id for a plain manual create', () => {
      const payload = buildCreatePayload(createValues())
      expect(payload).not.toHaveProperty('lead_id')
    })
  })
})
