import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload, createDefaults, toFormValues } from './commission-configuration-form-payload'
import type { CommissionConfigurationDetail } from './types'

const saved: CommissionConfigurationDetail = {
  id: 1,
  name: 'Rule',
  recipient_role: 'COMMERCIAL',
  application_scope: 'PRODUCT',
  product_category_id: null,
  product_category: null,
  product_id: 3,
  product: { id: 3, name: 'Product' },
  recipient_type: 'referent',
  recipient_id: 5,
  recipient: { id: 5, name: 'Jane' },
  commission_type: 'PERCENTAGE',
  value: '5.0000',
  priority: 1,
  valid_from: '2026-07-29',
  valid_until: null,
  status: 'ACTIVE',
  internal_note: null,
  created_at: '2026-07-29T00:00:00Z',
  updated_at: '2026-07-29T00:00:00Z',
}

describe('commission configuration form payload', () => {
  it('maps the saved record to form values, deriving the recipient type the actor cannot see', () => {
    expect(toFormValues(saved)).toMatchObject({ value: 5, recipient_type: 'referent', recipient_id: 5 })

    const hidden = { ...saved, recipient_role: 'SUPPLIER' as const, recipient_type: undefined, recipient_id: undefined }
    expect(toFormValues(hidden)).toMatchObject({
      recipient_type: 'registry',
      recipient_id: null,
    })
  })

  it('seeds a SUPPLIER rule on the given registry and a role-wide COMMERCIAL default otherwise', () => {
    expect(createDefaults(12)).toMatchObject({ recipient_role: 'SUPPLIER', application_scope: 'RECIPIENT', recipient_id: 12 })
    expect(createDefaults(null)).toMatchObject({ recipient_role: 'COMMERCIAL', application_scope: 'PRODUCT_CATEGORY', recipient_id: null })
  })

  it('nulls the relation the scope does not use and empty optional inputs on create', () => {
    const payload = buildCreatePayload({
      ...toFormValues(saved),
      application_scope: 'PRODUCT_CATEGORY',
      product_category_id: 8,
      product_id: 99,
      valid_until: '',
      internal_note: '',
    })

    expect(payload).toMatchObject({ product_category_id: 8, product_id: null, valid_until: null, internal_note: null })
  })

  it('sends nothing for an untouched form', () => {
    expect(buildUpdatePayload(toFormValues(saved), toFormValues(saved))).toEqual({})
  })

  it('sends only the changed fields of a row', () => {
    const original = toFormValues(saved)

    expect(buildUpdatePayload({ ...original, name: 'Renamed' }, original)).toEqual({ name: 'Renamed' })
    expect(buildUpdatePayload({ ...original, valid_from: '2026-08-01', valid_until: '2026-12-31' }, original)).toEqual({
      valid_from: '2026-08-01',
      valid_until: '2026-12-31',
    })
  })

  it('moves the scope with the cleared relation, and clears an emptied end date as null', () => {
    const original = toFormValues({ ...saved, valid_until: '2026-12-31' })

    expect(
      buildUpdatePayload(
        { ...original, application_scope: 'PRODUCT_CATEGORY', product_category_id: 8, valid_until: '' },
        original,
      ),
    ).toEqual({ application_scope: 'PRODUCT_CATEGORY', product_category_id: 8, product_id: null, valid_until: null })
  })
})
