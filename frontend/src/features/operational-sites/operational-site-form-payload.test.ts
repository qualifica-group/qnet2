import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/operational-sites/operational-site-form-payload'
import type { OperationalSiteDetailWithPermissions } from '@/features/operational-sites/types'
import type { OperationalSiteFormValues } from '@/features/operational-sites/use-operational-site-form'

/** Spec 0011 AC-019: create payload shape, update payload diffs only changes. */

const formValues: OperationalSiteFormValues = {
  alias: 'HQ',
  line1: 'Via Roma 1',
  postal_code: '20100',
  country_id: 1,
  state_id: 2,
  province_id: 3,
  city_id: 4,
  is_active: true,
  custom_fields: {},
}

function original(
  overrides: Partial<OperationalSiteDetailWithPermissions> = {},
): OperationalSiteDetailWithPermissions {
  return {
    id: 7,
    alias: 'HQ',
    line1: 'Via Roma 1',
    postal_code: '20100',
    country_id: 1,
    country: { id: 1, name: 'Italy' },
    state_id: 2,
    region: { id: 2, name: 'Lombardy' },
    province_id: 3,
    province: { id: 3, name: 'Milan' },
    city_id: 4,
    city: { id: 4, name: 'Milan' },
    is_active: true,
    created_at: '2026-01-01T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('builds the full create payload shape', () => {
    const payload = buildCreatePayload(formValues)

    expect(payload).toEqual({
      alias: 'HQ',
      line1: 'Via Roma 1',
      postal_code: '20100',
      country_id: 1,
      state_id: 2,
      province_id: 3,
      city_id: 4,
      is_active: true,
    })
  })

  it('sends alias null when left blank', () => {
    const payload = buildCreatePayload({ ...formValues, alias: '' })
    expect(payload.alias).toBeNull()
  })

  it('sends postal_code null when left blank', () => {
    const payload = buildCreatePayload({ ...formValues, postal_code: '' })
    expect(payload.postal_code).toBeNull()
  })

  it('carries null geo ids through unchanged (only country/state/province optional)', () => {
    const payload = buildCreatePayload({
      ...formValues,
      country_id: null,
      state_id: null,
      province_id: null,
    })

    expect(payload.country_id).toBeNull()
    expect(payload.state_id).toBeNull()
    expect(payload.province_id).toBeNull()
    expect(payload.city_id).toBe(4)
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    const payload = buildUpdatePayload(formValues, original())
    expect(payload).toEqual({})
  })

  it('includes only the changed line1', () => {
    const payload = buildUpdatePayload({ ...formValues, line1: 'Via Milano 2' }, original())
    expect(payload).toEqual({ line1: 'Via Milano 2' })
  })

  it('sends postal_code: null when the CAP is cleared', () => {
    const payload = buildUpdatePayload({ ...formValues, postal_code: '' }, original())
    expect(payload).toEqual({ postal_code: null })
  })

  it('includes a changed geo level', () => {
    const payload = buildUpdatePayload({ ...formValues, city_id: 9 }, original())
    expect(payload).toEqual({ city_id: 9 })
  })

  it('includes a changed alias (and sends null when cleared)', () => {
    expect(buildUpdatePayload({ ...formValues, alias: 'Milan office' }, original())).toEqual({
      alias: 'Milan office',
    })
    expect(buildUpdatePayload({ ...formValues, alias: '' }, original())).toEqual({ alias: null })
  })

  it('includes is_active only when toggled (spec 0135)', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
    expect(buildUpdatePayload({ ...formValues, is_active: false }, original())).toEqual({
      is_active: false,
    })
  })

  it('combines multiple changed fields in a single payload', () => {
    const payload = buildUpdatePayload(
      {
        alias: 'HQ',
        line1: 'Via Torino 3',
        postal_code: '10100',
        country_id: 1,
        state_id: 5,
        province_id: 6,
        city_id: 9,
        is_active: true,
        custom_fields: {},
      },
      original(),
    )

    expect(payload).toEqual({
      line1: 'Via Torino 3',
      postal_code: '10100',
      state_id: 5,
      province_id: 6,
      city_id: 9,
    })
  })
})
