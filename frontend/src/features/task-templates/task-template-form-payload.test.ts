import { describe, expect, it } from 'vitest'
import {
  buildBoardUpdatePayload,
  buildCreatePayload,
} from '@/features/task-templates/task-template-form-payload'
import type { TaskTemplateItemFormRow, TaskTemplateStageFormRow } from '@/features/task-templates/types'
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
    work_order_workflow_status_id: null,
    due_offset_days: 2,
    stage_key: null,
    parent_key: null,
    ...overrides,
  }
}

function stageRow(overrides: Partial<TaskTemplateStageFormRow> = {}): TaskTemplateStageFormRow {
  return { id: 'stage-1', name: 'Analisi', ...overrides }
}

describe('buildCreatePayload (spec 0124/0146)', () => {
  it('builds every row in visual order, never carrying an id', () => {
    const rows = [itemRow({ id: 'row-1', title: 'First' }), itemRow({ id: 'row-2', title: 'Second' })]

    expect(buildCreatePayload(formValues, rows, [])).toEqual({
      name: 'Standard onboarding',
      description: 'Used for new clients',
      is_active: true,
      items: [
        { title: 'First', description: null, estimated_minutes: 30, task_status_id: null, work_order_workflow_status_id: null, due_offset_days: 2, stage_key: null, key: 'row-1', parent_key: null },
        { title: 'Second', description: null, estimated_minutes: 30, task_status_id: null, work_order_workflow_status_id: null, due_offset_days: 2, stage_key: null, key: 'row-2', parent_key: null },
      ],
      stages: [],
    })
  })

  it('sends stages with their client key, and items carry the owning stage_key', () => {
    const stages = [stageRow({ id: 'new-stage-1', name: 'Analisi' })]
    const rows = [itemRow({ id: 'row-1', stage_key: 'new-stage-1' })]

    const payload = buildCreatePayload(formValues, rows, stages)

    expect(payload.stages).toEqual([{ key: 'new-stage-1', name: 'Analisi' }])
    expect(payload.items[0].stage_key).toBe('new-stage-1')
  })

  /** Spec 0172 D-1/D-2: a root's `key` is its own local id, a sub-item's `parent_key` is the root's own — root always first in the array. */
  it('sends each row own key and its parent_key, root before its subtree', () => {
    const rows = [
      itemRow({ id: 'root', title: 'Root' }),
      itemRow({ id: 'child', title: 'Child', parent_key: 'root' }),
    ]

    const payload = buildCreatePayload(formValues, rows, [])

    expect(payload.items[0]).toMatchObject({ key: 'root', parent_key: null })
    expect(payload.items[1]).toMatchObject({ key: 'child', parent_key: 'root' })
  })
})

describe('buildBoardUpdatePayload (spec 0227 D-3, full sync of spec 0124/0146 D-1/D-2)', () => {
  it('sends items and stages only, never a header field', () => {
    const payload = buildBoardUpdatePayload([itemRow({ itemId: 5 })], [])

    expect(Object.keys(payload).sort()).toEqual(['items', 'stages'])
    expect(payload.items).toEqual([
      {
        id: 5,
        title: 'Kickoff call',
        description: null,
        estimated_minutes: 30,
        task_status_id: null,
        work_order_workflow_status_id: null,
        due_offset_days: 2,
        stage_key: null,
        key: 'row-1',
        parent_key: null,
      },
    ])
    expect(payload.stages).toEqual([])
  })

  it('a new row (no itemId) is sent with an undefined id (dropped on JSON serialization)', () => {
    const payload = buildBoardUpdatePayload([itemRow({ id: 'new-1', itemId: undefined })], [])
    expect(payload.items?.[0].id).toBeUndefined()
  })

  it('sends persisted stages with their id and a new stage without one (D-2/AC-002)', () => {
    const stages = [stageRow({ id: 'stage-1', stageId: 1, name: 'Analisi' }), stageRow({ id: 'new-stage-1', name: 'Sviluppo' })]

    const payload = buildBoardUpdatePayload([], stages)

    expect(payload.stages).toEqual([
      { id: 1, key: 'stage-1', name: 'Analisi' },
      { id: undefined, key: 'new-stage-1', name: 'Sviluppo' },
    ])
  })
})
