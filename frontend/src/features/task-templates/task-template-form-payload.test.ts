import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/task-templates/task-template-form-payload'
import type { TaskTemplateDetail, TaskTemplateItemFormRow } from '@/features/task-templates/types'
import type { TaskTemplateFormValues } from '@/features/task-templates/use-task-template-form'

const formValues: TaskTemplateFormValues = {
  name: 'Standard onboarding',
  description: 'Used for new clients',
  is_active: true,
}

function itemRow(overrides: Partial<TaskTemplateItemFormRow> = {}): TaskTemplateItemFormRow {
  return {
    id: 'row-1',
    title: 'Kickoff call',
    description: null,
    estimated_minutes: 30,
    task_status_id: null,
    due_offset_days: 2,
    ...overrides,
  }
}

function original(overrides: Partial<TaskTemplateDetail> = {}): TaskTemplateDetail {
  return {
    id: 9,
    name: 'Standard onboarding',
    description: 'Used for new clients',
    is_active: true,
    items_count: 1,
    items: [],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload (spec 0124)', () => {
  it('builds every row in visual order, never carrying an id', () => {
    const rows = [itemRow({ id: 'row-1', title: 'First' }), itemRow({ id: 'row-2', title: 'Second' })]

    expect(buildCreatePayload(formValues, rows)).toEqual({
      name: 'Standard onboarding',
      description: 'Used for new clients',
      is_active: true,
      items: [
        { title: 'First', description: null, estimated_minutes: 30, task_status_id: null, due_offset_days: 2 },
        { title: 'Second', description: null, estimated_minutes: 30, task_status_id: null, due_offset_days: 2 },
      ],
    })
  })
})

describe('buildUpdatePayload (spec 0124, D-1)', () => {
  it('omits header fields when nothing changed, but always sends items', () => {
    const payload = buildUpdatePayload(formValues, [itemRow({ itemId: 5 })], original())

    expect(payload.name).toBeUndefined()
    expect(payload.description).toBeUndefined()
    expect(payload.is_active).toBeUndefined()
    expect(payload.items).toEqual([
      { id: 5, title: 'Kickoff call', description: null, estimated_minutes: 30, task_status_id: null, due_offset_days: 2 },
    ])
  })

  it('includes only the changed header field', () => {
    const payload = buildUpdatePayload({ ...formValues, name: 'Renamed' }, [], original())
    expect(payload.name).toBe('Renamed')
    expect(payload.description).toBeUndefined()
  })

  it('a new row (no itemId) is sent with an undefined id (dropped on JSON serialization)', () => {
    const payload = buildUpdatePayload(formValues, [itemRow({ id: 'new-1', itemId: undefined })], original())
    expect(payload.items?.[0].id).toBeUndefined()
  })

  it('a row missing from the array is implicitly deleted (full sync, no explicit assertion needed here — the array IS the sync)', () => {
    const payload = buildUpdatePayload(
      formValues,
      [itemRow({ id: 'row-1', itemId: 1 })],
      original({ items_count: 2 }),
    )
    expect(payload.items).toHaveLength(1)
  })
})
