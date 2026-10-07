import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateWorkOrderPaymentStatusSchema,
  buildUpdateWorkOrderPaymentStatusSchema,
} from '@/features/work-order-payment-statuses/work-order-payment-status-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID = {
  name: 'Paid',
  description: null,
  color: 'green',
  is_active: true,
  allows_delivery: true,
}

function parse(overrides: Record<string, unknown>) {
  return buildCreateWorkOrderPaymentStatusSchema(i18n.t).safeParse({ ...VALID, ...overrides })
}

describe('buildCreateWorkOrderPaymentStatusSchema', () => {
  it('accepts a valid payload', () => {
    expect(parse({}).success).toBe(true)
  })

  it('accepts a null description and rejects one over 500 characters', () => {
    expect(parse({ description: null }).success).toBe(true)
    expect(parse({ description: 'a'.repeat(501) }).success).toBe(false)
  })

  it('rejects an empty name and a name over 191 characters', () => {
    expect(parse({ name: '' }).success).toBe(false)
    expect(parse({ name: 'a'.repeat(192) }).success).toBe(false)
  })

  it('rejects a missing color and a color over 32 characters', () => {
    expect(parse({ color: '' }).success).toBe(false)
    expect(parse({ color: 'a'.repeat(33) }).success).toBe(false)
  })

  it('requires is_active and allows_delivery as booleans', () => {
    expect(parse({ is_active: undefined }).success).toBe(false)
    expect(parse({ allows_delivery: undefined }).success).toBe(false)
    expect(parse({ allows_delivery: 'yes' }).success).toBe(false)
  })

  it('does not carry a group (the lookup has none)', () => {
    const result = parse({ group: 'pending' })
    expect(result.success && 'group' in result.data).toBe(false)
  })
})

describe('buildUpdateWorkOrderPaymentStatusSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateWorkOrderPaymentStatusSchema(i18n.t)
    expect(schema.safeParse({ ...VALID, name: 'Unpaid', allows_delivery: false }).success).toBe(true)
  })
})
