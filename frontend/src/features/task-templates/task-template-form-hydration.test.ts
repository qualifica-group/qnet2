import { describe, expect, it } from 'vitest'
import {
  itemRowsFromDetail,
  newEmptyItemRow,
  newEmptySubtaskItemRow,
  stageKeyOf,
} from '@/features/task-templates/task-template-form-hydration'
import type { TaskTemplateDetail, TaskTemplateItem } from '@/features/task-templates/types'

function item(overrides: Partial<TaskTemplateItem> = {}): TaskTemplateItem {
  return {
    id: 1,
    title: 'Kickoff call',
    description: null,
    estimated_minutes: null,
    task_status_id: null,
    task_status: null,
    due_offset_days: 0,
    sort_order: 0,
    task_template_stage_id: null,
    parent_id: null,
    attachments: [],
    ...overrides,
  }
}

function taskTemplate(overrides: Partial<TaskTemplateDetail> = {}): TaskTemplateDetail {
  return {
    id: 1,
    name: 'Standard onboarding',
    description: null,
    is_active: true,
    items_count: 0,
    stages: [],
    items: [],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('newEmptyItemRow / newEmptySubtaskItemRow (spec 0172 D-1/D-3)', () => {
  it('builds a root row with no parent_key', () => {
    expect(newEmptyItemRow('new-1').parent_key).toBeNull()
  })

  it('builds a sub-task row nesting under the given parent row, never carrying its own stage_key', () => {
    const parent = { ...newEmptyItemRow('new-1'), stage_key: 'stage-1' }
    const subtask = newEmptySubtaskItemRow('new-2', parent)

    expect(subtask.parent_key).toBe('new-1')
    expect(subtask.stage_key).toBeNull()
  })
})

describe('itemRowsFromDetail (spec 0172 D-1/AC-018: rebuilds parent_key from parent_id)', () => {
  it('hydrates a root row with a null parent_key', () => {
    const rows = itemRowsFromDetail(taskTemplate({ items: [item({ id: 10, parent_id: null })] }))
    expect(rows[0]).toMatchObject({ id: '10', parent_key: null })
  })

  it('hydrates a child row local parent_key from the persisted parent_id', () => {
    const rows = itemRowsFromDetail(
      taskTemplate({
        items: [item({ id: 10, parent_id: null }), item({ id: 11, parent_id: 10 })],
      }),
    )
    expect(rows[1]).toMatchObject({ id: '11', parent_key: '10' })
  })

  it('keeps the stage_key resolution unaffected by nesting (mirrors stageKeyOf)', () => {
    const rows = itemRowsFromDetail(
      taskTemplate({ items: [item({ id: 10, task_template_stage_id: 3 })] }),
    )
    expect(rows[0].stage_key).toBe(stageKeyOf(3))
  })
})
