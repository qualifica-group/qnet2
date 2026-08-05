import { describe, expect, it } from 'vitest'
import { buildUpdatePayload } from '@/features/opportunities/opportunity-form-payload'
import { original, values } from '@/features/opportunities/opportunity-form-payload-fixtures'

/**
 * AC-076: the PATCH payload carries only the fields that actually changed
 * (sparse diff, mirrors leads/registries). Split off opportunity-form-payload.test.ts
 * when that file crossed the 500-line hard limit (engineering.md §6).
 */

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(values(), original())).toEqual({})
  })

  it('includes only the changed registry_id', () => {
    expect(buildUpdatePayload(values({ registry_id: 2 }), original())).toEqual({ registry_id: 2 })
  })

  it('allows an existing supervisor to be cleared back to null', () => {
    const payload = buildUpdatePayload(
      values({ supervisor_id: null }),
      original({ supervisor_id: 9, supervisor: { id: 9, name: 'Alex Smith' } }),
    )

    expect(payload).toEqual({ supervisor_id: null })
  })

  it('includes a relation cleared back to null', () => {
    const payload = buildUpdatePayload(
      values({ source_id: null }),
      original({ source_id: 4, source: { id: 4, name: 'Web' } }),
    )
    expect(payload).toEqual({ source_id: null })
  })

  it('includes multiple changed fields together', () => {
    const payload = buildUpdatePayload(
      values({ referent_id: 11, source_id: 7 }),
      original(),
    )
    expect(payload).toEqual({ referent_id: 11, source_id: 7 })
  })

  it('omits estimated_value when the server decimal string round-trips to the same number', () => {
    const payload = buildUpdatePayload(
      values({ estimated_value: 1500 }),
      original({ estimated_value: '1500.00' }),
    )
    expect(payload).toEqual({})
  })

  it('includes estimated_value when it actually changed', () => {
    const payload = buildUpdatePayload(
      values({ estimated_value: 2000 }),
      original({ estimated_value: '1500.00' }),
    )
    expect(payload).toEqual({ estimated_value: 2000 })
  })

  it('includes success_probability when changed', () => {
    const payload = buildUpdatePayload(
      values({ success_probability: 80 }),
      original({ success_probability: 40 }),
    )
    expect(payload).toEqual({ success_probability: 80 })
  })

  /** "Note generali" (2026-07-27): diffs independently, the null-clear is transmitted. */
  describe('general_notes', () => {
    it('includes general_notes when changed', () => {
      const payload = buildUpdatePayload(
        values({ general_notes: 'Nota aggiornata' }),
        original({ general_notes: 'Nota iniziale' }),
      )
      expect(payload).toEqual({ general_notes: 'Nota aggiornata' })
    })

    it('transmits an explicit null clear', () => {
      const payload = buildUpdatePayload(
        values({ general_notes: null }),
        original({ general_notes: 'Nota iniziale' }),
      )
      expect(payload).toEqual({ general_notes: null })
    })

    it('omits general_notes when untouched, treating a missing key as null', () => {
      const payload = buildUpdatePayload(values(), original())
      expect(payload).not.toHaveProperty('general_notes')
    })
  })

  /** "Note generali" (2026-07-27): diffs independently, the null-clear is transmitted. */
  describe('general_notes', () => {
    it('includes general_notes when changed', () => {
      const payload = buildUpdatePayload(
        values({ general_notes: 'Nota aggiornata' }),
        original({ general_notes: 'Nota iniziale' }),
      )
      expect(payload).toEqual({ general_notes: 'Nota aggiornata' })
    })

    it('transmits an explicit null clear', () => {
      const payload = buildUpdatePayload(
        values({ general_notes: null }),
        original({ general_notes: 'Nota iniziale' }),
      )
      expect(payload).toEqual({ general_notes: null })
    })

    it('omits general_notes when untouched, treating a missing key as null', () => {
      const payload = buildUpdatePayload(values(), original())
      expect(payload).not.toHaveProperty('general_notes')
    })
  })

  /** Spec 0056 (AC-003/004): diffs independently; the null-clear is transmitted, not omitted. */
  describe('operational_site_id (spec 0056)', () => {
    it('includes operational_site_id when changed', () => {
      const payload = buildUpdatePayload(values({ operational_site_id: 8 }), original({ operational_site_id: null }))
      expect(payload).toEqual({ operational_site_id: 8 })
    })

    it('omits operational_site_id when unchanged', () => {
      const payload = buildUpdatePayload(values({ operational_site_id: 8 }), original({ operational_site_id: 8 }))
      expect(payload).toEqual({})
    })

    it('sends an explicit null when the operational site is cleared (AC-004)', () => {
      const payload = buildUpdatePayload(
        values({ operational_site_id: null }),
        original({ operational_site_id: 8, operational_site: { id: 8, label: 'Warehouse A - Milan' } }),
      )
      expect(payload).toEqual({ operational_site_id: null })
    })
  })

  /** Spec 0047 (D1, AC-016/017): both diff independently, like every other field. */
  describe('state_id / opportunity_workflow_status_id (spec 0047)', () => {
    it('includes state_id when changed', () => {
      const payload = buildUpdatePayload(values({ state_id: 3 }), original({ state_id: null }))
      expect(payload).toEqual({ state_id: 3 })
    })

    it('omits state_id when unchanged', () => {
      const payload = buildUpdatePayload(values({ state_id: 3 }), original({ state_id: 3 }))
      expect(payload).toEqual({})
    })

    it('includes opportunity_workflow_status_id when changed', () => {
      const payload = buildUpdatePayload(
        values({ opportunity_workflow_status_id: 12 }),
        original({ opportunity_workflow_status_id: 11 }),
      )
      expect(payload).toEqual({ opportunity_workflow_status_id: 12 })
    })
  })

  describe('products_of_interest (unordered set diff, user directive 2026-07-22)', () => {
    it('omits the key when the set is unchanged, even reordered', () => {
      const payload = buildUpdatePayload(
        values({ products_of_interest: [7, 3] }),
        original({
          products_of_interest: [
            { id: 3, name: 'Fibra', product_category: { id: 11, name: 'Connettivita' } },
            { id: 7, name: 'Mobile', product_category: null },
          ],
        }),
      )
      expect(payload).toEqual({})
    })

    it('includes the whole set when a product was added or removed', () => {
      expect(
        buildUpdatePayload(values({ products_of_interest: [3, 7] }), original({ products_of_interest: [] })),
      ).toEqual({ products_of_interest: [3, 7] })

      expect(
        buildUpdatePayload(
          values({ products_of_interest: [] }),
          original({ products_of_interest: [{ id: 3, name: 'Fibra', product_category: null }] }),
        ),
      ).toEqual({ products_of_interest: [] })
    })
  })

  describe('rewards (unordered set diff, spec 0059 D-3)', () => {
    it('omits the key when the set is unchanged, even reordered', () => {
      const payload = buildUpdatePayload(
        values({ rewards: [{ reward_type_id: 7 }, { reward_type_id: 3 }] }),
        original({
          rewards: [
            { id: 900, reward_type: { id: 3, name: 'Amazon 10€', color: 'blue' }, assigned_at: '2026-01-01', notes: null },
            { id: 901, reward_type: { id: 7, name: 'Buono spesa', color: 'green' }, assigned_at: '2026-01-02', notes: null },
          ],
        }),
      )
      expect(payload).toEqual({})
    })

    it('includes the whole set when a reward was added or removed', () => {
      expect(
        buildUpdatePayload(values({ rewards: [{ reward_type_id: 3 }] }), original({ rewards: [] })),
      ).toEqual({ rewards: [{ reward_type_id: 3 }] })

      expect(
        buildUpdatePayload(
          values({ rewards: [] }),
          original({
            rewards: [
              { id: 900, reward_type: { id: 3, name: 'Amazon 10€', color: 'blue' }, assigned_at: '2026-01-01', notes: null },
            ],
          }),
        ),
      ).toEqual({ rewards: [] })
    })

    /**
     * Data-loss guard: the three states of the sparse contract (spec 0059
     * §4/`sync_semantics`) must never collapse. An untouched selection is a
     * KEY ABSENT from the payload (server: no-op); a fully-cleared selection
     * is `rewards: []` (server: delete every assignment). Sending `[]` for
     * "untouched" would silently wipe every reward on any save that doesn't
     * touch this field.
     */
    it('never collapses "untouched" (key absent) into an explicit clear ([])', () => {
      const originalRewards = {
        rewards: [
          { id: 900, reward_type: { id: 3, name: 'Amazon 10€', color: 'blue' }, assigned_at: '2026-01-01', notes: null },
        ],
      }

      const untouched = buildUpdatePayload(values({ rewards: [{ reward_type_id: 3 }] }), original(originalRewards))
      expect(untouched).not.toHaveProperty('rewards')

      const explicitlyCleared = buildUpdatePayload(values({ rewards: [] }), original(originalRewards))
      expect(explicitlyCleared).toHaveProperty('rewards', [])
    })
  })

  describe('product_lines (unordered set diff, amendment rev.3)', () => {
    it('omits product_lines when the set is unchanged, even reordered', () => {
      const payload = buildUpdatePayload(
        values({
          product_lines: [
            { business_function_id: 2, product_category_id: 22 },
            { business_function_id: 1, product_category_id: 11 },
          ],
        }),
        original({
          product_lines: [
            {
              id: 100,
              business_function: { id: 1, name: 'Sales' },
              product_category: { id: 11, name: 'Consulting' },
            },
            {
              id: 101,
              business_function: { id: 2, name: 'Marketing' },
              product_category: { id: 22, name: 'Training' },
            },
          ],
        }),
      )
      expect(payload).toEqual({})
    })

    it('includes product_lines when a row was added', () => {
      const payload = buildUpdatePayload(
        values({ product_lines: [{ business_function_id: 1, product_category_id: 11 }] }),
        original({ product_lines: [] }),
      )
      expect(payload).toEqual({ product_lines: [{ business_function_id: 1, product_category_id: 11 }] })
    })

    it('includes product_lines when every row is removed', () => {
      const payload = buildUpdatePayload(
        values({ product_lines: [] }),
        original({
          product_lines: [
            {
              id: 100,
              business_function: { id: 1, name: 'Sales' },
              product_category: { id: 11, name: 'Consulting' },
            },
          ],
        }),
      )
      expect(payload).toEqual({ product_lines: [] })
    })
  })

  describe('manager_slots (position- and gap-sensitive, rebuilt from `managers` refs)', () => {
    it('omits manager_slots when the form slots match the original managers, in position', () => {
      const payload = buildUpdatePayload(
        values({ manager_slots: [30, null, 31] }),
        original({
          managers: [
            { id: 30, name: 'Mario Rossi', position: 1 },
            { id: 31, name: 'Anna Bianchi', position: 3 },
          ],
        }),
      )
      expect(payload).toEqual({})
    })

    it('includes manager_slots when a slot changed', () => {
      const payload = buildUpdatePayload(
        values({ manager_slots: [32] }),
        original({ managers: [{ id: 30, name: 'Mario Rossi', position: 1 }] }),
      )
      expect(payload).toEqual({ manager_slots: [32] })
    })

    it('includes manager_slots when every manager is removed', () => {
      const payload = buildUpdatePayload(
        values({ manager_slots: [] }),
        original({ managers: [{ id: 30, name: 'Mario Rossi', position: 1 }] }),
      )
      expect(payload).toEqual({ manager_slots: [] })
    })
  })
})
