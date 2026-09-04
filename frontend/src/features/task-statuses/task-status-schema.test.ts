import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildCreateTaskStatusSchema,
  buildUpdateTaskStatusSchema,
} from '@/features/task-statuses/task-status-schema'

/**
 * Spec 0101 AC-046: `color` must be a palette TOKEN and `icon` a name of the
 * curated lucide catalogue, and `completion_percentage` an integer in 0..100.
 * `group` is the fixed 5-value phase enum, required on create. The client
 * mirrors the server allow-lists (defense in depth), it does not replace them.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const VALID_VALUES = {
  name: 'In progress',
  description: null,
  color: 'blue',
  icon: 'star',
  group: 'pending',
  is_active: true,
  completion_percentage: 25,
}

describe('buildCreateTaskStatusSchema (spec 0101)', () => {
  it('accepts a valid set of values', () => {
    const schema = buildCreateTaskStatusSchema(i18n.t)
    expect(schema.safeParse(VALID_VALUES).success).toBe(true)
  })

  it('rejects an empty name', () => {
    const schema = buildCreateTaskStatusSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, name: '' }).success).toBe(false)
  })

  it('rejects a name over 191 characters', () => {
    const schema = buildCreateTaskStatusSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, name: 'a'.repeat(192) }).success).toBe(false)
  })

  it('accepts a null description and rejects one over 500 characters', () => {
    const schema = buildCreateTaskStatusSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, description: null }).success).toBe(true)
    expect(schema.safeParse({ ...VALID_VALUES, description: 'a'.repeat(501) }).success).toBe(false)
  })

  it('requires a color: the backend column is NOT NULL (D-4)', () => {
    const schema = buildCreateTaskStatusSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, color: '' }).success).toBe(false)
  })

  it.each(['#ff0000', 'fuchsia', 'rgb(1,2,3)'])(
    'rejects a color outside the badge palette (%s)',
    (color) => {
      const schema = buildCreateTaskStatusSchema(i18n.t)
      expect(schema.safeParse({ ...VALID_VALUES, color }).success).toBe(false)
    },
  )

  it('accepts an empty icon (unset) and rejects one outside the curated catalogue', () => {
    const schema = buildCreateTaskStatusSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, icon: '' }).success).toBe(true)
    expect(schema.safeParse({ ...VALID_VALUES, icon: 'not-a-lucide-icon' }).success).toBe(false)
  })

  it.each([-1, 101, 12.5, Number.NaN])(
    'rejects a completion percentage outside the 0..100 integer range (%s)',
    (completion_percentage) => {
      const schema = buildCreateTaskStatusSchema(i18n.t)
      expect(schema.safeParse({ ...VALID_VALUES, completion_percentage }).success).toBe(false)
    },
  )

  it.each([0, 50, 100])('accepts the boundary percentages (%s)', (completion_percentage) => {
    const schema = buildCreateTaskStatusSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, completion_percentage }).success).toBe(true)
  })

  it.each(['open', 'pending', 'in_validation', 'closed_positive', 'closed_negative'] as const)(
    'accepts the group value "%s"',
    (group) => {
      const schema = buildCreateTaskStatusSchema(i18n.t)
      expect(schema.safeParse({ ...VALID_VALUES, group }).success).toBe(true)
    },
  )

  it('rejects a group value outside the fixed enum', () => {
    const schema = buildCreateTaskStatusSchema(i18n.t)
    expect(schema.safeParse({ ...VALID_VALUES, group: 'in_progress' }).success).toBe(false)
  })

  it('rejects a missing group (required on create)', () => {
    const schema = buildCreateTaskStatusSchema(i18n.t)
    const withoutGroup: Partial<typeof VALID_VALUES> = { ...VALID_VALUES }
    delete withoutGroup.group
    expect(schema.safeParse(withoutGroup).success).toBe(false)
  })
})

describe('buildUpdateTaskStatusSchema', () => {
  it('has the same shape as the create schema', () => {
    const schema = buildUpdateTaskStatusSchema(i18n.t)
    expect(schema.safeParse(VALID_VALUES).success).toBe(true)
    expect(schema.safeParse({ ...VALID_VALUES, group: 'closed_negative' }).success).toBe(true)
  })
})
