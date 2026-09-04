import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateTaskTypeSchema,
  buildUpdateTaskTypeSchema,
} from '@/features/task-types/task-type-schema'

/**
 * Spec 0101 AC-046: `color` must be a palette TOKEN and `icon` a name of the
 * curated lucide catalogue.
 * The client mirrors the server allow-lists (defense in depth), it does not
 * replace them.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID_VALUES = {
  name: 'Call',
  description: null,
  color: 'blue',
  icon: 'star',
  is_active: true,
}

describe('buildCreateTaskTypeSchema (spec 0101)', () => {
  it('accepts a valid set of values', () => {
    const schema = buildCreateTaskTypeSchema(i18n.t)
    expect(schema.safeParse(VALID_VALUES).success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateTaskTypeSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, name: '' }).success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateTaskTypeSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, name: 'a'.repeat(192) }).success).toBe(false)
  })

  it('accepts a null description and rejects one over 500 characters', () => {
    const schema = buildCreateTaskTypeSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, description: null }).success).toBe(true)
    expect(schema.safeParse({ ...VALID_VALUES, description: 'a'.repeat(501) }).success).toBe(false)
  })

  it('requires a color: the backend column is NOT NULL (D-4)', () => {
    const schema = buildCreateTaskTypeSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, color: '' }).success).toBe(false)
  })

  it.each(['#ff0000', 'fuchsia', 'rgb(1,2,3)'])(
    'rejects a color outside the badge palette (%s)',
    (color) => {
      const schema = buildCreateTaskTypeSchema(i18n.t)
      expect(schema.safeParse({ ...VALID_VALUES, color }).success).toBe(false)
    },
  )

  it('accepts an empty icon (unset) and rejects one outside the curated catalogue', () => {
    const schema = buildCreateTaskTypeSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, icon: '' }).success).toBe(true)
    expect(schema.safeParse({ ...VALID_VALUES, icon: 'not-a-lucide-icon' }).success).toBe(false)
  })
})

describe('buildUpdateTaskTypeSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateTaskTypeSchema(i18n.t)
    expect(schema.safeParse(VALID_VALUES).success).toBe(true)
  })
})
