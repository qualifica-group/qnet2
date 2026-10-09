import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/sectors/sector-form-payload'
import type { SectorDetail } from '@/features/sectors/types'
import type { SectorFormValues } from '@/features/sectors/use-sector-form'

/** Spec 0018 AC-019: create shape, update sends only the changed fields. */

function original(overrides: Partial<SectorDetail> = {}): SectorDetail {
  return {
    id: 4,
    name: 'Applications',
    parent_id: 1,
    parent: { id: 1, name: 'Enterprise Architecture' },
    is_active: true,
    created_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('builds the create payload with a root parent_id (null)', () => {
    const values: SectorFormValues = { name: 'Applications', parent_id: null, is_active: true, custom_fields: {} }

    expect(buildCreatePayload(values)).toEqual({
      name: 'Applications',
      parent_id: null,
      is_active: true,
    })
  })

  it('builds the create payload with a selected parent', () => {
    const values: SectorFormValues = { name: 'Applications', parent_id: 1, is_active: true, custom_fields: {} }

    expect(buildCreatePayload(values)).toEqual({ name: 'Applications', parent_id: 1, is_active: true })
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    const values: SectorFormValues = { name: 'Applications', parent_id: 1, is_active: true, custom_fields: {} }

    expect(buildUpdatePayload(values, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    const values: SectorFormValues = {
      name: 'Business Applications',
      parent_id: 1,
      is_active: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({ name: 'Business Applications' })
  })

  it('includes only the changed parent_id, including a move to root (null)', () => {
    const values: SectorFormValues = { name: 'Applications', parent_id: null, is_active: true, custom_fields: {} }

    expect(buildUpdatePayload(values, original())).toEqual({ parent_id: null })
  })

  it('includes both fields when both changed', () => {
    const values: SectorFormValues = {
      name: 'Business Applications',
      parent_id: 2,
      is_active: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({
      name: 'Business Applications',
      parent_id: 2,
    })
  })

  it('includes only the changed is_active (spec 0212)', () => {
    const values: SectorFormValues = { name: 'Applications', parent_id: 1, is_active: false, custom_fields: {} }

    expect(buildUpdatePayload(values, original())).toEqual({ is_active: false })
  })
})
