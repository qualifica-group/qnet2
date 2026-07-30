import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateDocumentLayoutSchema,
  buildUpdateDocumentLayoutSchema,
} from '@/features/document-layouts/document-layout-schema'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID_PAYLOAD = {
  name: 'Standard quote layout',
  code: 'standard_quote',
  description: null,
  module: 'quotes',
  is_active: true,
  is_default: false,
}

describe('buildCreateDocumentLayoutSchema (spec 0069, AC-110)', () => {
  it('accepts a valid payload', () => {
    const schema = buildCreateDocumentLayoutSchema(i18n.t)
    expect(schema.safeParse(VALID_PAYLOAD).success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateDocumentLayoutSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, name: '' }).success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateDocumentLayoutSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, name: 'a'.repeat(192) }).success).toBe(false)
  })

  it('accepts a name at exactly 191 characters', () => {
    const schema = buildCreateDocumentLayoutSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, name: 'a'.repeat(191) }).success).toBe(true)
  })

  it('rejects an empty code', () => {
    const schema = buildCreateDocumentLayoutSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code: '' }).success).toBe(false)
  })

  it('rejects a code over 64 characters', () => {
    const schema = buildCreateDocumentLayoutSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code: `s${'a'.repeat(64)}` }).success).toBe(false)
  })

  it.each(['Standard', '1abc', 'a-b', 'a b'])('rejects a code out of the regex shape (%s)', (code) => {
    const schema = buildCreateDocumentLayoutSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code }).success).toBe(false)
  })

  it('accepts a valid snake_case code', () => {
    const schema = buildCreateDocumentLayoutSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, code: 'standard_quote_2' }).success).toBe(true)
  })

  it('accepts a null description', () => {
    const schema = buildCreateDocumentLayoutSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, description: null }).success).toBe(true)
  })

  it('rejects a description over 500 characters', () => {
    const schema = buildCreateDocumentLayoutSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, description: 'a'.repeat(501) }).success).toBe(false)
  })

  it('accepts a description at exactly 500 characters', () => {
    const schema = buildCreateDocumentLayoutSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, description: 'a'.repeat(500) }).success).toBe(true)
  })

  it('rejects a module outside the enum', () => {
    const schema = buildCreateDocumentLayoutSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, module: 'invoices' }).success).toBe(false)
  })

  it('accepts the only supported module (quotes)', () => {
    const schema = buildCreateDocumentLayoutSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_PAYLOAD, module: 'quotes' }).success).toBe(true)
  })
})

describe('buildUpdateDocumentLayoutSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateDocumentLayoutSchema(i18n.t)
    expect(schema.safeParse(VALID_PAYLOAD).success).toBe(true)
  })
})
