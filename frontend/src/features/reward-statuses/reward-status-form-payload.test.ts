import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/reward-statuses/reward-status-form-payload'
import type { RewardStatusDetailWithPermissions } from '@/features/reward-statuses/types'
import type { RewardStatusFormValues } from '@/features/reward-statuses/use-reward-status-form'

const formValues: RewardStatusFormValues = {
  name: 'Approvato',
  description: 'Buono approvato',
  color: 'green',
  group: 'pending',
  is_active: true,
}

function original(
  overrides: Partial<RewardStatusDetailWithPermissions> = {},
): RewardStatusDetailWithPermissions {
  return {
    id: 7,
    name: 'Approvato',
    description: 'Buono approvato',
    color: 'green',
    group: 'pending',
    sort_order: 10,
    is_active: true,
    system_key: null,
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
  it('builds the full create payload shape', () => {
    expect(buildCreatePayload(formValues)).toEqual({
      name: 'Approvato',
      description: 'Buono approvato',
      color: 'green',
      group: 'pending',
      is_active: true,
    })
  })

  it('never includes sort_order or system_key (D-2/D-3: server-managed)', () => {
    expect(buildCreatePayload(formValues)).not.toHaveProperty('sort_order')
    expect(buildCreatePayload(formValues)).not.toHaveProperty('system_key')
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload({ ...formValues, name: 'Rifiutato' }, original())).toEqual({
      name: 'Rifiutato',
    })
  })

  it('includes only the changed description', () => {
    expect(buildUpdatePayload({ ...formValues, description: null }, original())).toEqual({
      description: null,
    })
  })

  it('includes only the changed color', () => {
    expect(buildUpdatePayload({ ...formValues, color: 'red' }, original())).toEqual({
      color: 'red',
    })
  })

  it('includes only the changed group (spec 0073)', () => {
    expect(buildUpdatePayload({ ...formValues, group: 'closed_lost' }, original())).toEqual({
      group: 'closed_lost',
    })
  })

  it('includes only the changed is_active', () => {
    expect(buildUpdatePayload({ ...formValues, is_active: false }, original())).toEqual({
      is_active: false,
    })
  })
})
