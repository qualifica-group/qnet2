import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/product-typologies/product-typology-form-payload'
import type { ProductTypologyDetailWithPermissions } from '@/features/product-typologies/types'
import type { ProductTypologyFormValues } from '@/features/product-typologies/use-product-typology-form'

const formValues: ProductTypologyFormValues = {
  name: 'Kilogram',
  code: 'kilogram',
  description: 'Mass unit',
  color: 'blue',
  supplier_commission_enabled: false,
  supplier_commission_direction: null,
}

function original(
  overrides: Partial<ProductTypologyDetailWithPermissions> = {},
): ProductTypologyDetailWithPermissions {
  return {
    id: 7,
    name: 'Kilogram',
      code: 'kilogram',
    description: 'Mass unit',
    color: 'blue',
    supplier_commission_enabled: false,
    supplier_commission_direction: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

describe('buildCreatePayload (spec 0099)', () => {
  it('builds the full create payload shape', () => {
    expect(buildCreatePayload(formValues)).toEqual({
      name: 'Kilogram',
      code: 'kilogram',
      description: 'Mass unit',
      color: 'blue',
      supplier_commission_enabled: false,
      supplier_commission_direction: null,
    })
  })
})

describe('buildUpdatePayload (spec 0099, D-1)', () => {
  it('sends only the color when it is the only change (spec 0204)', () => {
    expect(buildUpdatePayload({ ...formValues, color: 'amber' }, original())).toEqual({ color: 'amber' })
  })

  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload({ ...formValues, name: 'Gram' }, original())).toEqual({
      name: 'Gram',
    })
  })


  it('includes only the changed description, and clears it to null', () => {
    expect(buildUpdatePayload({ ...formValues, description: null }, original())).toEqual({
      description: null,
    })
  })

  it('NEVER includes code, even when the form value diverges from the original (D-1)', () => {
    const divergedCode = { ...formValues, code: 'gram' }
    const payload = buildUpdatePayload(divergedCode, original())
    expect(payload).not.toHaveProperty('code')
  })

  it('sends enabled and direction when the commission is switched on', () => {
    expect(
      buildUpdatePayload(
        { ...formValues, supplier_commission_enabled: true, supplier_commission_direction: 'PAID' },
        original(),
      ),
    ).toEqual({ supplier_commission_enabled: true, supplier_commission_direction: 'PAID' })
  })

  it('sends a null direction when the commission is switched off, even with a stale form direction', () => {
    const enabledOriginal = original({
      supplier_commission_enabled: true,
      supplier_commission_direction: 'RECEIVED',
    })
    expect(
      buildUpdatePayload(
        { ...formValues, supplier_commission_enabled: false, supplier_commission_direction: 'RECEIVED' },
        enabledOriginal,
      ),
    ).toEqual({ supplier_commission_enabled: false, supplier_commission_direction: null })
  })

  it('sends only the direction when just the direction changes', () => {
    const enabledOriginal = original({
      supplier_commission_enabled: true,
      supplier_commission_direction: 'RECEIVED',
    })
    expect(
      buildUpdatePayload(
        { ...formValues, supplier_commission_enabled: true, supplier_commission_direction: 'PAID' },
        enabledOriginal,
      ),
    ).toEqual({ supplier_commission_direction: 'PAID' })
  })
})
