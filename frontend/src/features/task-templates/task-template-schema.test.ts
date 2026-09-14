import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateTaskTemplateSchema,
  buildUpdateTaskTemplateSchema,
} from '@/features/task-templates/task-template-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID_PAYLOAD = {
  name: 'Standard onboarding',
  description: null,
  is_active: true,
}

describe('buildCreateTaskTemplateSchema (spec 0124)', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreateTaskTemplateSchema(i18n.t)
    expect(schema.safeParse(VALID_PAYLOAD).success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateTaskTemplateSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, name: '' }).success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateTaskTemplateSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, name: 'a'.repeat(192) }).success).toBe(false)
  })

  it('accepts a null description', () => {
    const schema = buildCreateTaskTemplateSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, description: null }).success).toBe(true)
  })
})

describe('buildUpdateTaskTemplateSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateTaskTemplateSchema(i18n.t)
    expect(schema.safeParse(VALID_PAYLOAD).success).toBe(true)
  })
})
