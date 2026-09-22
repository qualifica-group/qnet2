import { describe, expect, it } from 'vitest'
import {
  UNASSIGNED_STAGE_CONTAINER_ID,
  flattenStageGroups,
  groupItemRowsByStage,
  moveItemRowToStage,
} from '@/features/task-templates/task-template-item-stage-grouping'
import type { TaskTemplateItemFormRow, TaskTemplateStageFormRow } from '@/features/task-templates/types'

function stage(id: string, name: string): TaskTemplateStageFormRow {
  return { id, name }
}

function item(id: string, stageKey: string | null): TaskTemplateItemFormRow {
  return {
    id,
    title: `Item ${id}`,
    description: null,
    estimated_minutes: null,
    task_status_id: null,
    due_offset_days: 0,
    stage_key: stageKey,
  }
}

describe('groupItemRowsByStage', () => {
  it('groups rows in stage order with a trailing "Senza fase" group', () => {
    const stages = [stage('stage-1', 'Analisi'), stage('stage-2', 'Sviluppo')]
    const rows = [item('a', 'stage-2'), item('b', null), item('c', 'stage-1')]

    const groups = groupItemRowsByStage(rows, stages)

    expect(groups.map((group) => group.containerId)).toEqual(['stage-1', 'stage-2', UNASSIGNED_STAGE_CONTAINER_ID])
    expect(groups[0].rows.map((row) => row.id)).toEqual(['c'])
    expect(groups[1].rows.map((row) => row.id)).toEqual(['a'])
    expect(groups[2].rows.map((row) => row.id)).toEqual(['b'])
  })

  it('falls back an unresolved stage_key to "Senza fase" (e.g. a just-removed stage)', () => {
    const rows = [item('a', 'stage-removed')]

    const groups = groupItemRowsByStage(rows, [])

    expect(groups).toHaveLength(1)
    expect(groups[0].containerId).toBe(UNASSIGNED_STAGE_CONTAINER_ID)
    expect(groups[0].rows.map((row) => row.id)).toEqual(['a'])
  })
})

describe('flattenStageGroups', () => {
  it('reconstructs the flat wire order: stage order first, "Senza fase" last', () => {
    const stages = [stage('stage-1', 'Analisi'), stage('stage-2', 'Sviluppo')]
    const rows = [item('a', 'stage-2'), item('b', null), item('c', 'stage-1')]

    const flat = flattenStageGroups(groupItemRowsByStage(rows, stages))

    expect(flat.map((row) => row.id)).toEqual(['c', 'a', 'b'])
  })
})

describe('moveItemRowToStage', () => {
  const stages = [stage('stage-1', 'Analisi'), stage('stage-2', 'Sviluppo')]

  it('moves a row into another stage, retagging its stage_key', () => {
    const rows = [item('a', 'stage-1'), item('b', 'stage-1'), item('c', 'stage-2')]

    const result = moveItemRowToStage(rows, stages, 'a', 'stage-2', 0)

    const moved = result.find((row) => row.id === 'a')
    expect(moved?.stage_key).toBe('stage-2')
    // stage-2's group now starts with the moved row.
    const groups = groupItemRowsByStage(result, stages)
    expect(groups[1].rows.map((row) => row.id)).toEqual(['a', 'c'])
    expect(groups[0].rows.map((row) => row.id)).toEqual(['b'])
  })

  it('moves a row into "Senza fase"', () => {
    const rows = [item('a', 'stage-1')]

    const result = moveItemRowToStage(rows, stages, 'a', UNASSIGNED_STAGE_CONTAINER_ID, 0)

    expect(result.find((row) => row.id === 'a')?.stage_key).toBeNull()
  })

  it('reorders within the same stage without changing stage_key', () => {
    const rows = [item('a', 'stage-1'), item('b', 'stage-1'), item('c', 'stage-1')]

    const result = moveItemRowToStage(rows, stages, 'c', 'stage-1', 0)

    expect(result.map((row) => row.id)).toEqual(['c', 'a', 'b'])
    expect(result.find((row) => row.id === 'c')?.stage_key).toBe('stage-1')
  })

  it('returns the same reference when the row does not exist', () => {
    const rows = [item('a', 'stage-1')]

    const result = moveItemRowToStage(rows, stages, 'missing', 'stage-2', 0)

    expect(result).toBe(rows)
  })

  it('returns the same reference when the target container does not exist', () => {
    const rows = [item('a', 'stage-1')]

    const result = moveItemRowToStage(rows, stages, 'a', 'stage-missing', 0)

    expect(result).toBe(rows)
  })
})
