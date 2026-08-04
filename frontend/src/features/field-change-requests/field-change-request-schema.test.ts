import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildFieldChangeRequestSchema } from '@/features/field-change-requests/field-change-request-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildFieldChangeRequestSchema (spec 0078)', () => {
  it('accepts an omitted reason', () => {
    const schema = buildFieldChangeRequestSchema(i18n.t)
    expect(schema.safeParse({}).success).toBe(true)
  })

  it('accepts an explicit null reason', () => {
    const schema = buildFieldChangeRequestSchema(i18n.t)
    expect(schema.safeParse({ reason: null }).success).toBe(true)
  })

  it('accepts an empty string reason', () => {
    const schema = buildFieldChangeRequestSchema(i18n.t)
    expect(schema.safeParse({ reason: '' }).success).toBe(true)
  })

  it('accepts a reason up to 1000 characters', () => {
    const schema = buildFieldChangeRequestSchema(i18n.t)
    expect(schema.safeParse({ reason: 'a'.repeat(1000) }).success).toBe(true)
  })

  it('rejects a reason over 1000 characters', () => {
    const schema = buildFieldChangeRequestSchema(i18n.t)
    expect(schema.safeParse({ reason: 'a'.repeat(1001) }).success).toBe(false)
  })
})
