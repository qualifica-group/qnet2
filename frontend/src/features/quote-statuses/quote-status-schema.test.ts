import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateQuoteStatusSchema,
  buildUpdateQuoteStatusSchema,
} from '@/features/quote-statuses/quote-status-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildCreateQuoteStatusSchema', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreateQuoteStatusSchema(i18n.t)
    const result = schema.safeParse({ name: 'Bozza', color: 'blue', group: 'open' })
    expect(result.success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateQuoteStatusSchema(i18n.t)
    const result = schema.safeParse({ name: '', color: '', group: 'open' })
    expect(result.success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateQuoteStatusSchema(i18n.t)
    const result = schema.safeParse({ name: 'a'.repeat(192), color: '', group: 'open' })
    expect(result.success).toBe(false)
  })

  it('accepts an empty color (unset)', () => {
    const schema = buildCreateQuoteStatusSchema(i18n.t)
    const result = schema.safeParse({ name: 'Bozza', color: '', group: 'open' })
    expect(result.success).toBe(true)
  })

  it.each(['open', 'pending', 'closed_won', 'closed_lost'] as const)('accepts the group value "%s"', (group) => {
    const schema = buildCreateQuoteStatusSchema(i18n.t)
    const result = schema.safeParse({ name: 'Bozza', color: '', group })
    expect(result.success).toBe(true)
  })

  it('rejects a group value outside the fixed enum', () => {
    const schema = buildCreateQuoteStatusSchema(i18n.t)
    const result = schema.safeParse({ name: 'Bozza', color: '', group: 'archived' })
    expect(result.success).toBe(false)
  })

  it('rejects a missing group (required on create)', () => {
    const schema = buildCreateQuoteStatusSchema(i18n.t)
    const result = schema.safeParse({ name: 'Bozza', color: '' })
    expect(result.success).toBe(false)
  })
})

describe('buildUpdateQuoteStatusSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateQuoteStatusSchema(i18n.t)
    const result = schema.safeParse({ name: 'Rifiutata', color: 'red', group: 'closed_lost' })
    expect(result.success).toBe(true)
  })
})
