import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/work-order-payment-statuses/work-order-payment-status-form-payload'
import type { WorkOrderPaymentStatusDetailWithPermissions } from '@/features/work-order-payment-statuses/types'
import type { WorkOrderPaymentStatusFormValues } from '@/features/work-order-payment-statuses/use-work-order-payment-status-form'

const formValues: WorkOrderPaymentStatusFormValues = {
  name: 'Approvato',
  description: 'Paid in full',
  color: 'green',
  is_active: true,
  allows_delivery: false,
}

function original(
  overrides: Partial<WorkOrderPaymentStatusDetailWithPermissions> = {},
): WorkOrderPaymentStatusDetailWithPermissions {
  return {
    id: 7,
    name: 'Approvato',
    description: 'Paid in full',
    color: 'green',
    sort_order: 10,
    is_active: true,
    allows_delivery: false,
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
      description: 'Paid in full',
      color: 'green',
      is_active: true,
      allows_delivery: false,
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

  it('includes only the changed allows_delivery (spec 0201)', () => {
    expect(buildUpdatePayload({ ...formValues, allows_delivery: true }, original())).toEqual({
      allows_delivery: true,
    })
  })

  it('includes only the changed is_active', () => {
    expect(buildUpdatePayload({ ...formValues, is_active: false }, original())).toEqual({
      is_active: false,
    })
  })
})
