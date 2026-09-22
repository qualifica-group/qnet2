import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/task-templates/task-template-form-payload'
import type { TaskTemplateDetail, TaskTemplateItemFormRow, TaskTemplateStageFormRow } from '@/features/task-templates/types'
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
    stage_key: null,
    ...overrides,
  }
}

function stageRow(overrides: Partial<TaskTemplateStageFormRow> = {}): TaskTemplateStageFormRow {
  return { id: 'stage-1', name: 'Analisi', ...overrides }
}

function original(overrides: Partial<TaskTemplateDetail> = {}): TaskTemplateDetail {
  return {
    id: 9,
    name: 'Standard onboarding',
    description: 'Used for new clients',
    is_active: true,
    items_count: 1,
    stages: [],
    items: [],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload (spec 0124/0146)', () => {
  it('builds every row in visual order, never carrying an id', () => {
    const rows = [itemRow({ id: 'row-1', title: 'First' }), itemRow({ id: 'row-2', title: 'Second' })]

    expect(buildCreatePayload(formValues, rows, [])).toEqual({
      name: 'Standard onboarding',
      description: 'Used for new clients',
      is_active: true,
      items: [
        { title: 'First', description: null, estimated_minutes: 30, task_status_id: null, due_offset_days: 2, stage_key: null },
        { title: 'Second', description: null, estimated_minutes: 30, task_status_id: null, due_offset_days: 2, stage_key: null },
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
})

describe('buildUpdatePayload (spec 0124/0146, D-1/D-2)', () => {
  it('omits header fields when nothing changed, but always sends items and stages', () => {
    const payload = buildUpdatePayload(formValues, [itemRow({ itemId: 5 })], [], original())

    expect(payload.name).toBeUndefined()
    expect(payload.description).toBeUndefined()
    expect(payload.is_active).toBeUndefined()
    expect(payload.items).toEqual([
      {
        id: 5,
        title: 'Kickoff call',
        description: null,
        estimated_minutes: 30,
        task_status_id: null,
        due_offset_days: 2,
        stage_key: null,
      },
    ])
    expect(payload.stages).toEqual([])
  })

  it('includes only the changed header field', () => {
    const payload = buildUpdatePayload({ ...formValues, name: 'Renamed' }, [], [], original())
    expect(payload.name).toBe('Renamed')
    expect(payload.description).toBeUndefined()
  })

  /** Spec 0128 AC-024: the header description is a `RichTextHtml`, same wire contract as `name`. */
  it('sends the new HTML when the description changed, and null when it was cleared', () => {
    const changed = buildUpdatePayload({ ...formValues, description: '<p><strong>Ciao</strong></p>' }, [], [], original())
    expect(changed.description).toBe('<p><strong>Ciao</strong></p>')

    const cleared = buildUpdatePayload({ ...formValues, description: null }, [], [], original())
    expect(cleared).toHaveProperty('description', null)
  })

  it('a new row (no itemId) is sent with an undefined id (dropped on JSON serialization)', () => {
    const payload = buildUpdatePayload(formValues, [itemRow({ id: 'new-1', itemId: undefined })], [], original())
    expect(payload.items?.[0].id).toBeUndefined()
  })

  it('a row missing from the array is implicitly deleted (full sync, no explicit assertion needed here — the array IS the sync)', () => {
    const payload = buildUpdatePayload(
      formValues,
      [itemRow({ id: 'row-1', itemId: 1 })],
      [],
      original({ items_count: 2 }),
    )
    expect(payload.items).toHaveLength(1)
  })

  it('sends persisted stages with their id and a new stage without one (D-2/AC-002)', () => {
    const stages = [stageRow({ id: 'stage-1', stageId: 1, name: 'Analisi' }), stageRow({ id: 'new-stage-1', name: 'Sviluppo' })]

    const payload = buildUpdatePayload(formValues, [], stages, original())

    expect(payload.stages).toEqual([
      { id: 1, key: 'stage-1', name: 'Analisi' },
      { id: undefined, key: 'new-stage-1', name: 'Sviluppo' },
    ])
  })
})
