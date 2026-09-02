import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/leads/lead-form-payload'
import type { LeadDetail } from '@/features/leads/types'
import type { LeadFormValues } from '@/features/leads/use-lead-form'

/** AC-063: the PATCH payload carries only the fields that actually changed (sparse diff, mirrors campaigns). */

function values(overrides: Partial<LeadFormValues> = {}): LeadFormValues {
  return {
    registry_id: 10,
    campaign_id: 20,
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

function original(overrides: Partial<LeadDetail> = {}): LeadDetail {
  return {
    id: 1,
    registry_id: 10,
    registry: { id: 10, name: 'Mario Rossi' },
    campaign_id: 20,
    campaign: { id: 20, code: 'CMP-0001', name: 'Spring push' },
    lead_status: 'not_associated',
    operational_site_id: null,
    operational_site: null,
    source_id: null,
    source: null,
    operator_id: null,
    operator: null,
    notes: null,
    extra_fields: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('includes the required registry_id/campaign_id and the optional fields', () => {
    const payload = buildCreatePayload(
      values({ operational_site_id: 3, source_id: 4, operator_id: 5, state_id: 6, notes: 'Note' }),
    )

    expect(payload).toEqual({
      registry_id: 10,
      campaign_id: 20,
      operational_site_id: 3,
      source_id: 4,
      operator_id: 5,
      state_id: 6,
      notes: 'Note',
      extra_fields: null,
      products_of_interest: [],
      convert_to_opportunity: false,
    })
  })

  it('sends null for the unset optional fields', () => {
    const payload = buildCreatePayload(values())

    expect(payload).toEqual({
      registry_id: 10,
      campaign_id: 20,
      operational_site_id: null,
      source_id: null,
      operator_id: null,
      state_id: null,
      notes: null,
      extra_fields: null,
      products_of_interest: [],
      convert_to_opportunity: false,
    })
  })

  /** Spec 0094, D-5: the create payload carries the chosen product ids verbatim. */
  it('includes products_of_interest when products are chosen', () => {
    const payload = buildCreatePayload(values({ products_of_interest: [7, 9] }))

    expect(payload.products_of_interest).toEqual([7, 9])
  })

  /** Directive 2026-07-21: the Regione is a user input, sent unconditionally like the opportunity form. */
  it('sends state_id unconditionally, even when the convert checkbox is on', () => {
    const payload = buildCreatePayload(
      values({ convert_to_opportunity: true, state_id: 7, operator_id: null, operational_site_id: null }),
    )

    expect(payload.state_id).toBe(7)
    expect(payload.operator_id).toBeNull()
    expect(payload.operational_site_id).toBeNull()
  })

  it('includes extra_fields as an object when rows are set (AC-014)', () => {
    const payload = buildCreatePayload(
      values({ extra_fields: [{ key: 'Original column', value: 'foo' }] }),
    )

    expect(payload.extra_fields).toEqual({ 'Original column': 'foo' })
  })

  /** AC-043 (spec 0044): the create payload carries the checkbox's boolean verbatim. */
  it('includes convert_to_opportunity: true once the conversion control is on', () => {
    const payload = buildCreatePayload(
      values({ convert_to_opportunity: true, operator_id: 5, operational_site_id: 3 }),
    )

    expect(payload.convert_to_opportunity).toBe(true)
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(values(), original())).toEqual({})
  })

  it('includes only the changed notes', () => {
    expect(buildUpdatePayload(values({ notes: 'Updated note' }), original())).toEqual({
      notes: 'Updated note',
    })
  })

  it('includes only the changed campaign_id', () => {
    expect(buildUpdatePayload(values({ campaign_id: 99 }), original())).toEqual({ campaign_id: 99 })
  })

  it('includes multiple changed fields together', () => {
    const payload = buildUpdatePayload(
      values({ registry_id: 11, operational_site_id: 7 }),
      original(),
    )
    expect(payload).toEqual({ registry_id: 11, operational_site_id: 7 })
  })

  it('includes a field cleared back to null', () => {
    const payload = buildUpdatePayload(
      values({ source_id: null }),
      original({ source_id: 4, source: { id: 4, name: 'Web' } }),
    )
    expect(payload).toEqual({ source_id: null })
  })

  /** Directive 2026-07-21: the Regione is now a user-editable field, diffed like any other. */
  it('includes state_id when changed', () => {
    const payload = buildUpdatePayload(
      values({ state_id: 3 }),
      original({ state_id: null }),
    )
    expect(payload).toEqual({ state_id: 3 })
  })

  it('omits state_id when unchanged', () => {
    const payload = buildUpdatePayload(values({ state_id: 3 }), original({ state_id: 3 }))
    expect(payload).toEqual({})
  })

  it('omits extra_fields when the rows are unchanged (order-independent, AC-014)', () => {
    const payload = buildUpdatePayload(
      values({ extra_fields: [{ key: 'b', value: '2' }, { key: 'a', value: '1' }] }),
      original({ extra_fields: { a: '1', b: '2' } }),
    )
    expect(payload).toEqual({})
  })

  it('includes extra_fields when a value changed', () => {
    const payload = buildUpdatePayload(
      values({ extra_fields: [{ key: 'a', value: 'updated' }] }),
      original({ extra_fields: { a: '1' } }),
    )
    expect(payload).toEqual({ extra_fields: { a: 'updated' } })
  })

  it('includes extra_fields as null when every row is removed', () => {
    const payload = buildUpdatePayload(values({ extra_fields: [] }), original({ extra_fields: { a: '1' } }))
    expect(payload).toEqual({ extra_fields: null })
  })

  /** Spec 0094, D-5: order-independent sparse diff, mirrors extra_fields above. */
  it('omits products_of_interest when the selection is unchanged regardless of order', () => {
    const payload = buildUpdatePayload(
      values({ products_of_interest: [9, 7] }),
      original({ products_of_interest: [{ id: 7, name: 'A', product_category: null }, { id: 9, name: 'B', product_category: null }] }),
    )
    expect(payload).toEqual({})
  })

  it('includes products_of_interest when the selection changed', () => {
    const payload = buildUpdatePayload(
      values({ products_of_interest: [7] }),
      original({ products_of_interest: [{ id: 7, name: 'A', product_category: null }, { id: 9, name: 'B', product_category: null }] }),
    )
    expect(payload).toEqual({ products_of_interest: [7] })
  })

  it('includes products_of_interest: [] when the selection is cleared (AC-033)', () => {
    const payload = buildUpdatePayload(
      values({ products_of_interest: [] }),
      original({ products_of_interest: [{ id: 7, name: 'A', product_category: null }] }),
    )
    expect(payload).toEqual({ products_of_interest: [] })
  })

  /** Spec 0044: edit-mode conversion is out of scope, the PATCH payload never carries the flag. */
  it('never includes convert_to_opportunity, even if the form value were true', () => {
    const payload = buildUpdatePayload(
      values({ convert_to_opportunity: true, notes: 'Updated note' }),
      original(),
    )
    expect(payload).not.toHaveProperty('convert_to_opportunity')
  })
})
