import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildCreatePayload, buildUpdatePayload, valuesOfDetail } from '@/features/support-topics/support-topic-form-payload'
import { buildSupportTopicSchema, type SupportTopicFormValues } from '@/features/support-topics/support-topic-schema'
import type { SupportTopicDetail } from '@/features/support-topics/types'

/** Spec 0334 AC-018: the form mirrors the server rules (assignees min 1, watchers disjoint, due days 0..365). */

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

const VALID: SupportTopicFormValues = {
  name: 'Stampante',
  description: null,
  icon: 'printer',
  sort_order: 0,
  is_active: true,
  task_type_id: null,
  task_category_id: null,
  task_priority_id: 4,
  urgent_task_priority_id: null,
  task_importance_id: null,
  due_days: 2,
  urgent_due_days: null,
  is_private: false,
  requires_closure_feedback: false,
  assignee_ids: [10],
  watcher_ids: [11],
}

const DETAIL: SupportTopicDetail = {
  id: 1,
  name: 'Stampante',
  description: null,
  icon: 'printer',
  sort_order: 0,
  is_active: true,
  task_type: null,
  task_category: null,
  task_priority: { id: 4, name: 'Alta' },
  urgent_task_priority: null,
  task_importance: null,
  due_days: 2,
  urgent_due_days: null,
  is_private: false,
  requires_closure_feedback: false,
  assignees: [{ id: 10, name: 'Mario' }],
  watchers: [{ id: 11, name: 'Lucia' }],
  created_at: null,
  updated_at: null,
}

describe('buildSupportTopicSchema', () => {
  const schema = buildSupportTopicSchema(i18n.t)

  it('accepts a valid topic', () => {
    expect(schema.safeParse(VALID).success).toBe(true)
  })

  it('requires at least one assignee', () => {
    const result = schema.safeParse({ ...VALID, assignee_ids: [] })
    expect(result.error?.issues[0].path).toEqual(['assignee_ids'])
  })

  it('refuses a watcher that is also an assignee, on watcher_ids', () => {
    const result = schema.safeParse({ ...VALID, watcher_ids: [10] })
    expect(result.error?.issues[0].path).toEqual(['watcher_ids'])
  })

  it('bounds due days to 0..365 on both fields', () => {
    expect(schema.safeParse({ ...VALID, due_days: 366 }).success).toBe(false)
    expect(schema.safeParse({ ...VALID, urgent_due_days: -1 }).success).toBe(false)
    expect(schema.safeParse({ ...VALID, urgent_due_days: 0 }).success).toBe(true)
  })

  it('requires a name', () => {
    expect(schema.safeParse({ ...VALID, name: '' }).success).toBe(false)
  })
})

describe('support topic payload builders', () => {
  it('maps an unset icon to null on create', () => {
    expect(buildCreatePayload({ ...VALID, icon: '' }).icon).toBeNull()
  })

  it('round-trips the saved record through the form values', () => {
    expect(valuesOfDetail(DETAIL)).toEqual(VALID)
  })

  it('sends only the changed keys on update, comparing id lists regardless of order', () => {
    expect(buildUpdatePayload({ ...VALID, name: 'Altro', assignee_ids: [10] }, DETAIL)).toEqual({ name: 'Altro' })
    expect(buildUpdatePayload({ ...VALID, assignee_ids: [10, 12] }, DETAIL)).toEqual({ assignee_ids: [10, 12] })
  })
})
