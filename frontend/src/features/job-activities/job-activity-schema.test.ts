import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildJobActivitySchema } from '@/features/job-activities/job-activity-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID = { name: 'Cleaning', description: null }

describe('buildJobActivitySchema (spec 0271)', () => {
  const schema = () => buildJobActivitySchema(i18n.t)

  it('accepts a valid payload', () => {
    expect(schema().safeParse(VALID).success).toBe(true)
  })

  it('rejects an empty name', () => {
    expect(schema().safeParse({ ...VALID, name: '' }).success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    expect(schema().safeParse({ ...VALID, name: 'a'.repeat(192) }).success).toBe(false)
  })

  it('rejects a description over 1000 characters', () => {
    expect(schema().safeParse({ ...VALID, description: 'a'.repeat(1001) }).success).toBe(false)
  })
})
