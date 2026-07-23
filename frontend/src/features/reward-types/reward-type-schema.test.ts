import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateRewardTypeSchema,
  buildUpdateRewardTypeSchema,
} from '@/features/reward-types/reward-type-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildCreateRewardTypeSchema', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreateRewardTypeSchema(i18n.t)
    const result = schema.safeParse({ name: 'Buono Amazon', color: 'green' })
    expect(result.success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateRewardTypeSchema(i18n.t)
    const result = schema.safeParse({ name: '', color: 'green' })
    expect(result.success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateRewardTypeSchema(i18n.t)
    const result = schema.safeParse({ name: 'a'.repeat(192), color: 'green' })
    expect(result.success).toBe(false)
  })

  it('rejects an empty color (D-5, D-5b: the picker "X" reset produces "")', () => {
    const schema = buildCreateRewardTypeSchema(i18n.t)
    const result = schema.safeParse({ name: 'Buono Amazon', color: '' })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path[0] === 'color')).toBe(true)
    }
  })

  it('rejects a missing color', () => {
    const schema = buildCreateRewardTypeSchema(i18n.t)
    const result = schema.safeParse({ name: 'Buono Amazon' })
    expect(result.success).toBe(false)
  })

  it('rejects a color over 32 characters', () => {
    const schema = buildCreateRewardTypeSchema(i18n.t)
    const result = schema.safeParse({ name: 'Buono Amazon', color: 'a'.repeat(33) })
    expect(result.success).toBe(false)
  })
})

describe('buildUpdateRewardTypeSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateRewardTypeSchema(i18n.t)
    const result = schema.safeParse({ name: 'Buono Amazon', color: 'blue' })
    expect(result.success).toBe(true)
  })

  it('rejects an empty color, same as create (color is never nullable, D-5)', () => {
    const schema = buildUpdateRewardTypeSchema(i18n.t)
    const result = schema.safeParse({ name: 'Buono Amazon', color: '' })
    expect(result.success).toBe(false)
  })
})
