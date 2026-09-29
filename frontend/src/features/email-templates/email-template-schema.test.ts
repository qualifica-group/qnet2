import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateEmailTemplateSchema,
  buildUpdateEmailTemplateSchema,
} from '@/features/email-templates/email-template-schema'

/**
 * Spec 0175 `data_contract`: `name` max 191, `subject` max 255, `body` is a
 * required rich text field (D-11) that must reject an empty/whitespace-only
 * HTML fragment even though `RichTextEditor` emits `null` for that case.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID_VALUES = {
  name: 'Follow-up',
  module: 'work_orders' as const,
  subject: 'Update on {work_order.code}',
  body: '<p>Hello {client.name}</p>',
  description: null,
  is_active: true,
}

describe('buildCreateEmailTemplateSchema (spec 0175)', () => {
  it('accepts a valid set of values', () => {
    const schema = buildCreateEmailTemplateSchema(i18n.t)
    expect(schema.safeParse(VALID_VALUES).success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateEmailTemplateSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, name: '' }).success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateEmailTemplateSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, name: 'a'.repeat(192) }).success).toBe(false)
  })

  it('rejects an empty subject and one over 255 characters', () => {
    const schema = buildCreateEmailTemplateSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, subject: '' }).success).toBe(false)
    expect(schema.safeParse({ ...VALID_VALUES, subject: 'a'.repeat(256) }).success).toBe(false)
  })

  it('rejects a null body (RichTextEditor emits null for empty content)', () => {
    const schema = buildCreateEmailTemplateSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, body: null }).success).toBe(false)
  })

  it('rejects a whitespace/tag-only body with no visible text', () => {
    const schema = buildCreateEmailTemplateSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, body: '   ' }).success).toBe(false)
  })

  it('accepts a null description and rejects one over 500 characters', () => {
    const schema = buildCreateEmailTemplateSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, description: null }).success).toBe(true)
    expect(schema.safeParse({ ...VALID_VALUES, description: 'a'.repeat(501) }).success).toBe(false)
  })

  it('rejects a module outside the admitted enum (D-10)', () => {
    const schema = buildCreateEmailTemplateSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, module: 'quotes' }).success).toBe(false)
  })
})

describe('buildUpdateEmailTemplateSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateEmailTemplateSchema(i18n.t)
    expect(schema.safeParse(VALID_VALUES).success).toBe(true)
  })
})
