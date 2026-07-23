import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/reward-types/reward-type-form-payload'
import type { RewardTypeDetailWithPermissions } from '@/features/reward-types/types'
import type { RewardTypeFormValues } from '@/features/reward-types/use-reward-type-form'

const formValues: RewardTypeFormValues = {
  name: 'Buono Amazon',
  color: 'green',
}

function original(
  overrides: Partial<RewardTypeDetailWithPermissions> = {},
): RewardTypeDetailWithPermissions {
  return {
    id: 7,
    name: 'Buono Amazon',
    color: 'green',
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

describe('buildCreatePayload', () => {
  it('builds the full create payload shape, name and color verbatim', () => {
    expect(buildCreatePayload(formValues)).toEqual({
      name: 'Buono Amazon',
      color: 'green',
    })
  })

  it('does NOT map an empty color to null (D-5: color is never nullable, unlike the opportunity-statuses template)', () => {
    expect(buildCreatePayload({ ...formValues, color: '' })).toEqual({
      name: 'Buono Amazon',
      color: '',
    })
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload({ ...formValues, name: 'Buono Esselunga' }, original())).toEqual({
      name: 'Buono Esselunga',
    })
  })

  it('includes only the changed color, verbatim (never mapped to null)', () => {
    expect(buildUpdatePayload({ ...formValues, color: 'blue' }, original())).toEqual({
      color: 'blue',
    })
  })
})
