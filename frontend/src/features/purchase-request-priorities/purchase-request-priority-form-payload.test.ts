import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/purchase-request-priorities/purchase-request-priority-form-payload'
import type { PurchaseRequestPriorityDetail } from '@/features/purchase-request-priorities/types'
import type { PurchaseRequestPriorityFormValues } from '@/features/purchase-request-priorities/use-purchase-request-priority-form'

/**
 * Spec 0247 `data_contract`: `sort_order` is never accepted on
 * write, so it must never appear in either payload.
 */

const formValues: PurchaseRequestPriorityFormValues = {
  name: 'High',
  description: 'Follow-up on the client request',
  color: 'blue',
  icon: 'star',
  is_active: true,
  is_default: false,
}

function original(overrides: Partial<PurchaseRequestPriorityDetail> = {}): PurchaseRequestPriorityDetail {
  return {
    id: 7,
    name: 'High',
    description: 'Follow-up on the client request',
    color: 'blue',
    icon: 'star',
    sort_order: 3,
    is_active: true,
    is_default: false,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload (spec 0247)', () => {
  it('builds the full create payload shape', () => {
    expect(buildCreatePayload(formValues)).toEqual({
      name: 'High',
      color: 'blue',
      icon: 'star',
      description: 'Follow-up on the client request',
      is_active: true,
      is_default: false,
    })
  })

  it('maps an unset icon onto null', () => {
    expect(buildCreatePayload({ ...formValues, icon: '' }).icon).toBeNull()
  })

  it('never carries sort_order', () => {
    const payload = buildCreatePayload(formValues)
    expect(payload).not.toHaveProperty('sort_order')
  })
})

describe('buildUpdatePayload (spec 0247)', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload({ ...formValues, name: 'Renamed' }, original())).toEqual({
      name: 'Renamed',
    })
  })

  it('clears the icon to null when the picker is emptied', () => {
    expect(buildUpdatePayload({ ...formValues, icon: '' }, original())).toEqual({ icon: null })
  })

  it('includes only the changed color', () => {
    expect(buildUpdatePayload({ ...formValues, color: 'emerald' }, original())).toEqual({
      color: 'emerald',
    })
  })

  it('never carries sort_order, even on a fully changed form', () => {
    const payload = buildUpdatePayload(
      { ...formValues, name: 'Renamed', color: 'teal', icon: '', is_active: false },
      original(),
    )
    expect(payload).not.toHaveProperty('sort_order')
  })

  it('includes only the changed is_default (spec 0247 D-3)', () => {
    expect(buildUpdatePayload({ ...formValues, is_default: true }, original())).toEqual({
      is_default: true,
    })
  })
})
