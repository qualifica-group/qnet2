import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateContractStatusSchema,
  buildUpdateContractStatusSchema,
} from '@/features/contract-statuses/contract-status-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID_PAYLOAD = {
  name: 'Da validare',
  description: null,
  color: 'blue',
  group: 'open',
  is_active: true,
  is_default: false,
}

describe('buildCreateContractStatusSchema', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreateContractStatusSchema(i18n.t)
    const result = schema.safeParse(VALID_PAYLOAD)
    expect(result.success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateContractStatusSchema(i18n.t)
    const result = schema.safeParse({ ...VALID_PAYLOAD, name: '' })
    expect(result.success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateContractStatusSchema(i18n.t)
    const result = schema.safeParse({ ...VALID_PAYLOAD, name: 'a'.repeat(192) })
    expect(result.success).toBe(false)
  })

  it('accepts a null description (unset)', () => {
    const schema = buildCreateContractStatusSchema(i18n.t)
    const result = schema.safeParse({ ...VALID_PAYLOAD, description: null })
    expect(result.success).toBe(true)
  })

  it('rejects a description over 500 characters', () => {
    const schema = buildCreateContractStatusSchema(i18n.t)
    const result = schema.safeParse({ ...VALID_PAYLOAD, description: 'a'.repeat(501) })
    expect(result.success).toBe(false)
  })

  it('accepts a description at exactly 500 characters', () => {
    const schema = buildCreateContractStatusSchema(i18n.t)
    const result = schema.safeParse({ ...VALID_PAYLOAD, description: 'a'.repeat(500) })
    expect(result.success).toBe(true)
  })

  it('accepts an empty color (unset)', () => {
    const schema = buildCreateContractStatusSchema(i18n.t)
    const result = schema.safeParse({ ...VALID_PAYLOAD, color: '' })
    expect(result.success).toBe(true)
  })

  it.each(['open', 'pending', 'closed_won', 'closed_lost'] as const)(
    'accepts the group value "%s"',
    (group) => {
      const schema = buildCreateContractStatusSchema(i18n.t)
      const result = schema.safeParse({ ...VALID_PAYLOAD, group })
      expect(result.success).toBe(true)
    },
  )

  it('rejects a group value outside the fixed enum', () => {
    const schema = buildCreateContractStatusSchema(i18n.t)
    const result = schema.safeParse({ ...VALID_PAYLOAD, group: 'archived' })
    expect(result.success).toBe(false)
  })

  it('rejects a missing group (required on create)', () => {
    const schema = buildCreateContractStatusSchema(i18n.t)
    const withoutGroup: Partial<typeof VALID_PAYLOAD> = { ...VALID_PAYLOAD }
    delete withoutGroup.group
    const result = schema.safeParse(withoutGroup)
    expect(result.success).toBe(false)
  })

  describe('BR-5 — is_default requires is_active', () => {
    it('rejects is_default = true with is_active = false, on the is_active field', () => {
      const schema = buildCreateContractStatusSchema(i18n.t)
      const result = schema.safeParse({ ...VALID_PAYLOAD, is_active: false, is_default: true })
      expect(result.success).toBe(false)
      if (!result.success) {
        expect(result.error.issues[0].path).toEqual(['is_active'])
      }
    })

    it('accepts is_default = true with is_active = true', () => {
      const schema = buildCreateContractStatusSchema(i18n.t)
      const result = schema.safeParse({ ...VALID_PAYLOAD, is_active: true, is_default: true })
      expect(result.success).toBe(true)
    })

    it('accepts is_default = false with is_active = false', () => {
      const schema = buildCreateContractStatusSchema(i18n.t)
      const result = schema.safeParse({ ...VALID_PAYLOAD, is_active: false, is_default: false })
      expect(result.success).toBe(true)
    })
  })
})

describe('buildUpdateContractStatusSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateContractStatusSchema(i18n.t)
    const result = schema.safeParse({ ...VALID_PAYLOAD, name: 'Sospeso', group: 'closed_lost' })
    expect(result.success).toBe(true)
  })
})
