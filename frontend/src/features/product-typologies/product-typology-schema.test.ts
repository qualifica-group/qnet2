import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateProductTypologySchema,
  buildUpdateProductTypologySchema,
} from '@/features/product-typologies/product-typology-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID_PAYLOAD = {
  name: 'Kilogram',
  code: 'kilogram',
  description: null,
  color: 'blue',
  supplier_commission_enabled: false,
  supplier_commission_direction: null,
}

describe('buildCreateProductTypologySchema (spec 0099)', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreateProductTypologySchema(i18n.t)
    expect(schema.safeParse(VALID_PAYLOAD).success).toBe(true)
  })

  it('requires a color (spec 0204 D-3)', () => {
    const schema = buildCreateProductTypologySchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, color: '' }).success).toBe(false)
    expect(schema.safeParse({ ...VALID_PAYLOAD, color: undefined }).success).toBe(false)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateProductTypologySchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, name: '' }).success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateProductTypologySchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, name: 'a'.repeat(192) }).success).toBe(false)
  })



  it('rejects an empty code', () => {
    const schema = buildCreateProductTypologySchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code: '' }).success).toBe(false)
  })

  it('rejects a code over 64 characters', () => {
    const schema = buildCreateProductTypologySchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code: `k${'a'.repeat(64)}` }).success).toBe(false)
  })

  it.each(['Kilogrammo', '1abc', 'a-b', 'a b'])('rejects a code out of the regex shape (%s)', (code) => {
    const schema = buildCreateProductTypologySchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code }).success).toBe(false)
  })

  it('accepts a valid snake_case code', () => {
    const schema = buildCreateProductTypologySchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code: 'kilogram_2' }).success).toBe(true)
  })

  it('accepts a null description', () => {
    const schema = buildCreateProductTypologySchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, description: null }).success).toBe(true)
  })

  it('rejects a description over 500 characters', () => {
    const schema = buildCreateProductTypologySchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, description: 'a'.repeat(501) }).success).toBe(false)
  })
})

describe('buildUpdateProductTypologySchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateProductTypologySchema(i18n.t)
    expect(schema.safeParse(VALID_PAYLOAD).success).toBe(true)
  })
})

describe('supplier commission direction (spec 0202 D-7)', () => {
  it('rejects an enabled commission without direction', () => {
    const schema = buildCreateProductTypologySchema(i18n.t)
    const result = schema.safeParse({ ...VALID_PAYLOAD, supplier_commission_enabled: true })
    expect(result.success).toBe(false)
    expect(result.error?.issues[0].path).toEqual(['supplier_commission_direction'])
  })

  it.each(['RECEIVED', 'PAID'])('accepts an enabled commission with direction %s', (direction) => {
    const schema = buildUpdateProductTypologySchema(i18n.t)
    expect(
      schema.safeParse({
        ...VALID_PAYLOAD,
        supplier_commission_enabled: true,
        supplier_commission_direction: direction,
      }).success,
    ).toBe(true)
  })

  it('rejects a direction outside the enum', () => {
    const schema = buildCreateProductTypologySchema(i18n.t)
    expect(
      schema.safeParse({
        ...VALID_PAYLOAD,
        supplier_commission_enabled: true,
        supplier_commission_direction: 'OTHER',
      }).success,
    ).toBe(false)
  })
})
