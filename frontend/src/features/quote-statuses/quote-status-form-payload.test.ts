import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/quote-statuses/quote-status-form-payload'
import type { QuoteStatusDetailWithPermissions } from '@/features/quote-statuses/types'
import type { QuoteStatusFormValues } from '@/features/quote-statuses/use-quote-status-form'

const formValues: QuoteStatusFormValues = {
  name: 'Bozza',
  color: 'blue',
  group: 'open',
}

function original(
  overrides: Partial<QuoteStatusDetailWithPermissions> = {},
): QuoteStatusDetailWithPermissions {
  return {
    id: 7,
    name: 'Bozza',
    color: 'blue',
    sort_order: 10,
    system_key: null,
    group: 'open',
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
    expect(buildCreatePayload(formValues)).toEqual({
      name: 'Bozza',
      color: 'blue',
      group: 'open',
    })
  })

  it('maps an empty color to null', () => {
    expect(buildCreatePayload({ ...formValues, color: '' })).toEqual({
      name: 'Bozza',
      color: null,
      group: 'open',
    })
  })

  it('never includes sort_order (server-managed)', () => {
    expect(buildCreatePayload(formValues)).not.toHaveProperty('sort_order')
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload({ ...formValues, name: 'Rifiutata' }, original())).toEqual({
      name: 'Rifiutata',
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
})
