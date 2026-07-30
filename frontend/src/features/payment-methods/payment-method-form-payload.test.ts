import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/payment-methods/payment-method-form-payload'
import type { PaymentMethodDetailWithPermissions } from '@/features/payment-methods/types'
import type { PaymentMethodFormValues } from '@/features/payment-methods/use-payment-method-form'

const formValues: PaymentMethodFormValues = {
  name: 'Bank transfer',
  code: 'bank_transfer',
  description: 'Standard bank transfer',
  payment_instructions: 'Use IBAN IT00X0000000000000000000000',
  payment_days: 30,
  is_active: true,
}

function original(
  overrides: Partial<PaymentMethodDetailWithPermissions> = {},
): PaymentMethodDetailWithPermissions {
  return {
    id: 7,
    name: 'Bank transfer',
    code: 'bank_transfer',
    description: 'Standard bank transfer',
    payment_instructions: 'Use IBAN IT00X0000000000000000000000',
    payment_days: 30,
    sort_order: 10,
    is_active: true,
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

describe('buildCreatePayload (spec 0068, AC-101)', () => {
  it('builds the full create payload shape', () => {
    expect(buildCreatePayload(formValues)).toEqual({
      name: 'Bank transfer',
      code: 'bank_transfer',
      description: 'Standard bank transfer',
      payment_instructions: 'Use IBAN IT00X0000000000000000000000',
      payment_days: 30,
      is_active: true,
    })
  })

  it('never includes sort_order (D-1: server-managed)', () => {
    expect(buildCreatePayload(formValues)).not.toHaveProperty('sort_order')
  })
})

describe('buildUpdatePayload (spec 0068, AC-101/AC-105)', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload({ ...formValues, name: 'Wire transfer' }, original())).toEqual({
      name: 'Wire transfer',
    })
  })

  it('includes only the changed description', () => {
    expect(buildUpdatePayload({ ...formValues, description: null }, original())).toEqual({
      description: null,
    })
  })

  it('includes only the changed payment_instructions', () => {
    expect(
      buildUpdatePayload({ ...formValues, payment_instructions: null }, original()),
    ).toEqual({ payment_instructions: null })
  })

  it('includes only the changed payment_days', () => {
    expect(buildUpdatePayload({ ...formValues, payment_days: 60 }, original())).toEqual({
      payment_days: 60,
    })
  })

  it('includes only the changed is_active', () => {
    expect(buildUpdatePayload({ ...formValues, is_active: false }, original())).toEqual({
      is_active: false,
    })
  })

  it('NEVER includes code, even when the form value diverges from the original (D-3)', () => {
    const divergedCode = { ...formValues, code: 'wire_transfer' }
    const payload = buildUpdatePayload(divergedCode, original())
    expect(payload).not.toHaveProperty('code')
  })
})
