import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCostTypeSchema } from '@/features/cost-types/cost-type-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID = { name: 'Collaboratori', description: null, sort_order: 0, is_active: true }

describe('buildCostTypeSchema (spec 0210)', () => {
  const schema = () => buildCostTypeSchema(i18n.t)

  it('accepts a valid payload', () => {
    expect(schema().safeParse(VALID).success).toBe(true)
  })

  it('rejects an empty name', () => {
    expect(schema().safeParse({ ...VALID, name: '' }).success).toBe(false)
  })

  it('rejects a name over 100 characters', () => {
    expect(schema().safeParse({ ...VALID, name: 'a'.repeat(101) }).success).toBe(false)
  })

  it('rejects a description over 500 characters', () => {
    expect(schema().safeParse({ ...VALID, description: 'a'.repeat(501) }).success).toBe(false)
  })

  it.each([-1, 1.5, Number.NaN])('rejects an invalid sort order (%s)', (sortOrder) => {
    expect(schema().safeParse({ ...VALID, sort_order: sortOrder }).success).toBe(false)
  })
})
