import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/units-of-measure/unit-of-measure-form-payload'
import type { UnitOfMeasureDetailWithPermissions } from '@/features/units-of-measure/types'
import type { UnitOfMeasureFormValues } from '@/features/units-of-measure/use-unit-of-measure-form'

const formValues: UnitOfMeasureFormValues = {
  name: 'Kilogram',
  symbol: 'kg',
  code: 'kilogram',
  description: 'Mass unit',
}

function original(
  overrides: Partial<UnitOfMeasureDetailWithPermissions> = {},
): UnitOfMeasureDetailWithPermissions {
  return {
    id: 7,
    name: 'Kilogram',
    symbol: 'kg',
    code: 'kilogram',
    description: 'Mass unit',
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

describe('buildCreatePayload (spec 0088)', () => {
  it('builds the full create payload shape', () => {
    expect(buildCreatePayload(formValues)).toEqual({
      name: 'Kilogram',
      symbol: 'kg',
      code: 'kilogram',
      description: 'Mass unit',
    })
  })
})

describe('buildUpdatePayload (spec 0088, D-1)', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload({ ...formValues, name: 'Gram' }, original())).toEqual({
      name: 'Gram',
    })
  })

  it('includes only the changed symbol', () => {
    expect(buildUpdatePayload({ ...formValues, symbol: 'g' }, original())).toEqual({
      symbol: 'g',
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
})
