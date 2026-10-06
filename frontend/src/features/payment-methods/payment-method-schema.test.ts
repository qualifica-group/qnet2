import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreatePaymentMethodSchema,
  buildUpdatePaymentMethodSchema,
} from '@/features/payment-methods/payment-method-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID_PAYLOAD = {
  name: 'Bank transfer',
  code: 'bank_transfer',
  payment_method_code: null,
  description: null,
  payment_instructions: null,
  payment_days: null,
  installments_count: 1,
  days_between_installments: 0,
  end_of_month: false,
  end_of_month_extra_days: null,
  vat_allocation: 'split',
  is_active: true,
}

describe('buildCreatePaymentMethodSchema (spec 0068, AC-100)', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse(VALID_PAYLOAD).success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, name: '' }).success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, name: 'a'.repeat(192) }).success).toBe(false)
  })

  it('rejects an empty code', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code: '' }).success).toBe(false)
  })

  it('rejects a code over 64 characters', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code: `b${'a'.repeat(64)}` }).success).toBe(false)
  })

  it.each(['Bonifico', '1abc', 'a-b', 'a b'])('rejects a code out of the regex shape (%s)', (code) => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code }).success).toBe(false)
  })

  it('accepts a valid snake_case code', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code: 'bank_transfer_2' }).success).toBe(true)
  })

  it('accepts a null description', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, description: null }).success).toBe(true)
  })

  it('rejects a description over 500 characters', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, description: 'a'.repeat(501) }).success).toBe(false)
  })

  it('rejects payment_instructions over 5000 characters', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(
      schema.safeParse({ ...VALID_PAYLOAD, payment_instructions: 'a'.repeat(5001) }).success,
    ).toBe(false)
  })

  it('accepts payment_days null', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, payment_days: null }).success).toBe(true)
  })

  it('rejects a negative payment_days', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, payment_days: -1 }).success).toBe(false)
  })

  it('rejects a non-integer payment_days', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, payment_days: 1.5 }).success).toBe(false)
  })

  it('rejects a payment_days over 3650', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, payment_days: 3651 }).success).toBe(false)
  })

  it('accepts the payment_days boundaries 0 and 3650', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, payment_days: 0 }).success).toBe(true)
    expect(schema.safeParse({ ...VALID_PAYLOAD, payment_days: 3650 }).success).toBe(true)
  })
})

describe('buildUpdatePaymentMethodSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse(VALID_PAYLOAD).success).toBe(true)
  })
})

describe('installment rules (spec 0194 D-10)', () => {
  it('rejects vat_first with a single installment', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, vat_allocation: 'vat_first' }).success).toBe(false)
    expect(
      schema.safeParse({ ...VALID_PAYLOAD, vat_allocation: 'vat_first', installments_count: 2 }).success,
    ).toBe(true)
  })

  it('requires days_between_installments with more than one installment', () => {
    const schema = buildUpdatePaymentMethodSchema(i18n.t)
    const values = { ...VALID_PAYLOAD, installments_count: 3, days_between_installments: null }
    expect(schema.safeParse(values).success).toBe(false)
    expect(schema.safeParse({ ...values, days_between_installments: 30 }).success).toBe(true)
  })

  it('rejects installments_count outside 1..60', () => {
    const schema = buildCreatePaymentMethodSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, installments_count: 0 }).success).toBe(false)
    expect(schema.safeParse({ ...VALID_PAYLOAD, installments_count: 61 }).success).toBe(false)
  })
})
