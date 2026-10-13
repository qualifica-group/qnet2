import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/invoice-tags/invoice-tag-form-payload'
import type { InvoiceTagDetail } from '@/features/invoice-tags/types'
import type { InvoiceTagFormValues } from '@/features/invoice-tags/use-invoice-tag-form'

/**
 * Spec 0247 `data_contract`: `sort_order` is never accepted on
 * write, so it must never appear in either payload.
 */

const formValues: InvoiceTagFormValues = {
  name: 'High',
  description: 'Follow-up on the client request',
  color: 'blue',
  is_active: true,
}

function original(overrides: Partial<InvoiceTagDetail> = {}): InvoiceTagDetail {
  return {
    id: 7,
    name: 'High',
    description: 'Follow-up on the client request',
    color: 'blue',
    sort_order: 3,
    is_active: true,
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
      description: 'Follow-up on the client request',
      is_active: true,
    })
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

  it('includes only the changed color', () => {
    expect(buildUpdatePayload({ ...formValues, color: 'emerald' }, original())).toEqual({
      color: 'emerald',
    })
  })

  it('never carries sort_order, even on a fully changed form', () => {
    const payload = buildUpdatePayload(
      { ...formValues, name: 'Renamed', color: 'teal', is_active: false },
      original(),
    )
    expect(payload).not.toHaveProperty('sort_order')
  })

  it('includes only the changed is_active', () => {
    expect(buildUpdatePayload({ ...formValues, is_active: false }, original())).toEqual({
      is_active: false,
    })
  })
})
