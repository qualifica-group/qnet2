import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateUnitOfMeasureSchema,
  buildUpdateUnitOfMeasureSchema,
} from '@/features/units-of-measure/unit-of-measure-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID_PAYLOAD = {
  name: 'Kilogram',
  symbol: 'kg',
  code: 'kilogram',
  description: null,
}

describe('buildCreateUnitOfMeasureSchema (spec 0088)', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreateUnitOfMeasureSchema(i18n.t)
    expect(schema.safeParse(VALID_PAYLOAD).success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateUnitOfMeasureSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, name: '' }).success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateUnitOfMeasureSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, name: 'a'.repeat(192) }).success).toBe(false)
  })

  it('rejects an empty symbol', () => {
    const schema = buildCreateUnitOfMeasureSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, symbol: '' }).success).toBe(false)
  })

  it('rejects a symbol over 16 characters', () => {
    const schema = buildCreateUnitOfMeasureSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, symbol: 'a'.repeat(17) }).success).toBe(false)
  })

  it('rejects an empty code', () => {
    const schema = buildCreateUnitOfMeasureSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code: '' }).success).toBe(false)
  })

  it('rejects a code over 64 characters', () => {
    const schema = buildCreateUnitOfMeasureSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code: `k${'a'.repeat(64)}` }).success).toBe(false)
  })

  it.each(['Kilogrammo', '1abc', 'a-b', 'a b'])('rejects a code out of the regex shape (%s)', (code) => {
    const schema = buildCreateUnitOfMeasureSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code }).success).toBe(false)
  })

  it('accepts a valid snake_case code', () => {
    const schema = buildCreateUnitOfMeasureSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code: 'kilogram_2' }).success).toBe(true)
  })

  it('accepts a null description', () => {
    const schema = buildCreateUnitOfMeasureSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, description: null }).success).toBe(true)
  })

  it('rejects a description over 500 characters', () => {
    const schema = buildCreateUnitOfMeasureSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, description: 'a'.repeat(501) }).success).toBe(false)
  })
})

describe('buildUpdateUnitOfMeasureSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateUnitOfMeasureSchema(i18n.t)
    expect(schema.safeParse(VALID_PAYLOAD).success).toBe(true)
  })
})
