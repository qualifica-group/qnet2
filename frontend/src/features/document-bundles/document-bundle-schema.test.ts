import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateDocumentBundleSchema,
  buildUpdateDocumentBundleSchema,
} from '@/features/document-bundles/document-bundle-schema'

/** Spec 0175 `data_contract`: `name` max 191 (unique), `description` nullable. */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID_VALUES = {
  name: 'Onboarding kit',
  description: null,
  is_active: true,
}

describe('buildCreateDocumentBundleSchema (spec 0175)', () => {
  it('accepts a valid set of values', () => {
    const schema = buildCreateDocumentBundleSchema(i18n.t)
    expect(schema.safeParse(VALID_VALUES).success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateDocumentBundleSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, name: '' }).success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateDocumentBundleSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, name: 'a'.repeat(192) }).success).toBe(false)
  })

  it('accepts a null description and rejects one over 500 characters', () => {
    const schema = buildCreateDocumentBundleSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, description: null }).success).toBe(true)
    expect(schema.safeParse({ ...VALID_VALUES, description: 'a'.repeat(501) }).success).toBe(false)
  })
})

describe('buildUpdateDocumentBundleSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateDocumentBundleSchema(i18n.t)
    expect(schema.safeParse(VALID_VALUES).success).toBe(true)
  })
})
