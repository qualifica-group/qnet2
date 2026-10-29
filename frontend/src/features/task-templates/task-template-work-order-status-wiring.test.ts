import { describe, expect, it } from 'vitest'
import { itemRowsFromDetail, newEmptyItemRow } from '@/features/task-templates/task-template-form-hydration'
import { buildBoardUpdatePayload, buildCreatePayload } from '@/features/task-templates/task-template-form-payload'
import type { TaskTemplateDetail, TaskTemplateItem } from '@/features/task-templates/types'

/** Spec 0323 AC-020: the row's `work_order_workflow_status_id` is hydrated and sent (null when cleared). */

const FORM_VALUES = { name: 'Modello', description: null, is_active: true }

function savedItem(overrides: Partial<TaskTemplateItem> = {}): TaskTemplateItem {
  return {
    id: 11,
    title: 'Collaudo',
    description: null,
    estimated_minutes: null,
    task_status_id: null,
    task_status: null,
    work_order_workflow_status_id: 7,
    work_order_workflow_status: {
      id: 7,
      name: 'Collaudo',
      color: null,
      progress_percentage: 60,
      work_order_workflow_id: 3,
      work_order_workflow_name: 'Impianti',
    },
    due_offset_days: 0,
    sort_order: 0,
    task_template_stage_id: null,
    parent_id: null,
    attachments: [],
    ...overrides,
  }
}

function detail(items: TaskTemplateItem[]): TaskTemplateDetail {
  return {
    id: 1,
    name: 'Modello',
    description: null,
    is_active: true,
    items_count: items.length,
    stages: [],
    items,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
  }
}

describe('work order status wiring', () => {
  it('hydrates the saved link into the editable row', () => {
    const [row] = itemRowsFromDetail(detail([savedItem()]))

    expect(row.work_order_workflow_status_id).toBe(7)
  })

  it('starts a new row unlinked', () => {
    expect(newEmptyItemRow('new-1').work_order_workflow_status_id).toBeNull()
  })

  it('sends the linked id on create and on the board update', () => {
    const rows = itemRowsFromDetail(detail([savedItem()]))

    expect(buildCreatePayload(FORM_VALUES, rows, []).items[0].work_order_workflow_status_id).toBe(7)
    expect(buildBoardUpdatePayload(rows, []).items?.[0].work_order_workflow_status_id).toBe(7)
  })

  it('sends null once the link was cleared', () => {
    const [row] = itemRowsFromDetail(detail([savedItem()]))
    const cleared = { ...row, work_order_workflow_status_id: null }

    expect(buildBoardUpdatePayload([cleared], []).items?.[0]).toMatchObject({ work_order_workflow_status_id: null })
  })
})
