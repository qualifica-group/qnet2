import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/contract-statuses/contract-status-form-payload'
import type { ContractStatusDetailWithPermissions } from '@/features/contract-statuses/types'
import type { ContractStatusFormValues } from '@/features/contract-statuses/use-contract-status-form'

const formValues: ContractStatusFormValues = {
  name: 'Da validare',
  description: 'Contratto in attesa di validazione',
  color: 'blue',
  group: 'open',
  is_active: true,
  is_default: true,
}

function original(
  overrides: Partial<ContractStatusDetailWithPermissions> = {},
): ContractStatusDetailWithPermissions {
  return {
    id: 7,
    name: 'Da validare',
    description: 'Contratto in attesa di validazione',
    color: 'blue',
    sort_order: 0,
    is_active: true,
    is_default: true,
    system_key: 'new',
    group: 'open',
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
      name: 'Da validare',
      description: 'Contratto in attesa di validazione',
      color: 'blue',
      group: 'open',
      is_active: true,
      is_default: true,
    })
  })

  it('maps an empty color to null', () => {
    expect(buildCreatePayload({ ...formValues, color: '' })).toEqual({
      name: 'Da validare',
      description: 'Contratto in attesa di validazione',
      color: null,
      group: 'open',
      is_active: true,
      is_default: true,
    })
  })

  it('never includes sort_order or system_key (server-managed)', () => {
    const payload = buildCreatePayload(formValues)
    expect(payload).not.toHaveProperty('sort_order')
    expect(payload).not.toHaveProperty('system_key')
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload({ ...formValues, name: 'Sospeso' }, original())).toEqual({
      name: 'Sospeso',
    })
  })

  it('includes only the changed description', () => {
    expect(buildUpdatePayload({ ...formValues, description: null }, original())).toEqual({
      description: null,
    })
  })

  it('includes only the changed color, mapping empty to null', () => {
    expect(buildUpdatePayload({ ...formValues, color: '' }, original())).toEqual({
      color: null,
    })
  })

  it('includes only the changed group', () => {
    expect(buildUpdatePayload({ ...formValues, group: 'pending' }, original())).toEqual({
      group: 'pending',
    })
  })

  it('includes only the changed is_active', () => {
    expect(buildUpdatePayload({ ...formValues, is_active: false }, original())).toEqual({
      is_active: false,
    })
  })

  it('includes only the changed is_default', () => {
    expect(buildUpdatePayload({ ...formValues, is_default: false }, original())).toEqual({
      is_default: false,
    })
  })
})
