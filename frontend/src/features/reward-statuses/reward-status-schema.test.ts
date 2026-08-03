import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateRewardStatusSchema,
  buildUpdateRewardStatusSchema,
} from '@/features/reward-statuses/reward-status-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildCreateRewardStatusSchema', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreateRewardStatusSchema(i18n.t)
    const result = schema.safeParse({
      name: 'Approvato',
      description: null,
      color: 'green',
      group: 'pending',
      is_active: true,
    })
    expect(result.success).toBe(true)
  })

  it('rejects a missing or unknown group (spec 0073)', () => {
    const schema = buildCreateRewardStatusSchema(i18n.t)

    expect(
      schema.safeParse({ name: 'Approvato', description: null, color: 'green', is_active: true })
        .success,
    ).toBe(false)
    expect(
      schema.safeParse({
        name: 'Approvato',
        description: null,
        color: 'green',
        group: 'closed',
        is_active: true,
      }).success,
    ).toBe(false)
  })

  it('rejects an empty name (AC-024)', () => {
    const schema = buildCreateRewardStatusSchema(i18n.t)
    const result = schema.safeParse({ name: '', description: null, color: 'green', is_active: true })
    expect(result.success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateRewardStatusSchema(i18n.t)
    const result = schema.safeParse({
      name: 'a'.repeat(192),
      description: null,
      color: 'green',
      is_active: true,
    })
    expect(result.success).toBe(false)
  })

  it('rejects a missing color (D-4, AC-024)', () => {
    const schema = buildCreateRewardStatusSchema(i18n.t)
    const result = schema.safeParse({
      name: 'Approvato',
      description: null,
      color: '',
      is_active: true,
    })
    expect(result.success).toBe(false)
  })

  it('rejects a color over 32 characters', () => {
    const schema = buildCreateRewardStatusSchema(i18n.t)
    const result = schema.safeParse({
      name: 'Approvato',
      description: null,
      color: 'a'.repeat(33),
      is_active: true,
    })
    expect(result.success).toBe(false)
  })

  it('accepts a null description (optional, AC-024)', () => {
    const schema = buildCreateRewardStatusSchema(i18n.t)
    const result = schema.safeParse({
      name: 'Approvato',
      description: null,
      color: 'green',
      group: 'open',
      is_active: true,
    })
    expect(result.success).toBe(true)
  })

  it('rejects a description over 500 characters', () => {
    const schema = buildCreateRewardStatusSchema(i18n.t)
    const result = schema.safeParse({
      name: 'Approvato',
      description: 'a'.repeat(501),
      color: 'green',
      is_active: true,
    })
    expect(result.success).toBe(false)
  })

  it('rejects a missing is_active', () => {
    const schema = buildCreateRewardStatusSchema(i18n.t)
    const result = schema.safeParse({ name: 'Approvato', description: null, color: 'green' })
    expect(result.success).toBe(false)
  })
})

describe('buildUpdateRewardStatusSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateRewardStatusSchema(i18n.t)
    const result = schema.safeParse({
      name: 'Rifiutato',
      description: 'Buono rifiutato',
      color: 'red',
      group: 'closed_lost',
      is_active: false,
    })
    expect(result.success).toBe(true)
  })
})
