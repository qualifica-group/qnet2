import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateInvoiceTagSchema,
  buildUpdateInvoiceTagSchema,
} from '@/features/invoice-tags/invoice-tag-schema'

/**
 * Spec 0247: `color` must be a palette TOKEN.
 * The client mirrors the server allow-lists (defense in depth), it does not
 * replace them.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID_VALUES = {
  name: 'High',
  description: null,
  color: 'blue',
  is_active: true,
}

describe('buildCreateInvoiceTagSchema (spec 0247)', () => {
  it('accepts a valid set of values', () => {
    const schema = buildCreateInvoiceTagSchema(i18n.t)
    expect(schema.safeParse(VALID_VALUES).success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateInvoiceTagSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, name: '' }).success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateInvoiceTagSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, name: 'a'.repeat(192) }).success).toBe(false)
  })

  it('accepts a null description and rejects one over 500 characters', () => {
    const schema = buildCreateInvoiceTagSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, description: null }).success).toBe(true)
    expect(schema.safeParse({ ...VALID_VALUES, description: 'a'.repeat(501) }).success).toBe(false)
  })

  it('requires a color: the backend column is NOT NULL (spec 0247 D-5)', () => {
    const schema = buildCreateInvoiceTagSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, color: '' }).success).toBe(false)
  })

  it.each(['#ff0000', 'fuchsia', 'rgb(1,2,3)'])(
    'rejects a color outside the badge palette (%s)',
    (color) => {
      const schema = buildCreateInvoiceTagSchema(i18n.t)
      expect(schema.safeParse({ ...VALID_VALUES, color }).success).toBe(false)
    },
  )
})

describe('buildUpdateInvoiceTagSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateInvoiceTagSchema(i18n.t)
    expect(schema.safeParse(VALID_VALUES).success).toBe(true)
  })
})
