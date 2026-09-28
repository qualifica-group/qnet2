import { describe, expect, it } from 'vitest'
import {
  UNASSIGNED_STAGE_CONTAINER_ID,
  flattenStageGroups,
  groupItemRowsByStage,
  moveItemRowToStage,
} from '@/features/task-templates/task-template-item-stage-grouping'
import type { TaskTemplateItemFormRow, TaskTemplateStageFormRow } from '@/features/task-templates/types'

function row(overrides: Partial<TaskTemplateItemFormRow> = {}): TaskTemplateItemFormRow {
  return {
    id: 'row-1',
    title: 'Kickoff call',
    description: null,
    estimated_minutes: null,
    task_status_id: null,
    due_offset_days: 0,
    stage_key: null,
    parent_key: null,
    ...overrides,
  }
}

function stage(overrides: Partial<TaskTemplateStageFormRow> = {}): TaskTemplateStageFormRow {
  return { id: 'stage-1', name: 'Analisi', ...overrides }
}

describe('groupItemRowsByStage (spec 0172 D-1/D-3)', () => {
  it('groups a root by its own stage_key', () => {
    const groups = groupItemRowsByStage([row({ id: 'root', stage_key: 'stage-1' })], [stage()])

    const analisi = groups.find((group) => group.containerId === 'stage-1')
    expect(analisi?.entries).toEqual([{ row: expect.objectContaining({ id: 'root' }), depth: 0 }])
    expect(analisi?.rootRows.map((r) => r.id)).toEqual(['root'])
  })

  it('a sub-item (stage_key always null, D-3) follows its root INTO the root own stage group, at depth 1', () => {
    const rows = [
      row({ id: 'root', stage_key: 'stage-1' }),
      row({ id: 'child', parent_key: 'root', stage_key: null }),
    ]
    const groups = groupItemRowsByStage(rows, [stage()])

    const analisi = groups.find((group) => group.containerId === 'stage-1')
    expect(analisi?.entries.map((e) => [e.row.id, e.depth])).toEqual([
      ['root', 0],
      ['child', 1],
    ])
    // The sub-item is NOT a root: it never joins `rootRows` (not draggable on its own).
    expect(analisi?.rootRows.map((r) => r.id)).toEqual(['root'])
  })

  it('computes depth transitively for a grandchild/great-grandchild chain', () => {
    const rows = [
      row({ id: 'root' }),
      row({ id: 'child', parent_key: 'root' }),
      row({ id: 'grandchild', parent_key: 'child' }),
      row({ id: 'great-grandchild', parent_key: 'grandchild' }),
    ]
    const groups = groupItemRowsByStage(rows, [])

    const unassigned = groups.find((group) => group.containerId === UNASSIGNED_STAGE_CONTAINER_ID)
    expect(unassigned?.entries.map((e) => e.depth)).toEqual([0, 1, 2, 3])
  })

  it('always trails a "Senza fase" group', () => {
    const groups = groupItemRowsByStage([], [stage()])
    expect(groups.at(-1)?.containerId).toBe(UNASSIGNED_STAGE_CONTAINER_ID)
  })
})

describe('flattenStageGroups (round-trip with groupItemRowsByStage)', () => {
  it('reproduces the original depth-first order', () => {
    const rows = [
      row({ id: 'root-1', stage_key: 'stage-1' }),
      row({ id: 'child-1', parent_key: 'root-1' }),
      row({ id: 'root-2', stage_key: null }),
    ]
    const groups = groupItemRowsByStage(rows, [stage()])

    expect(flattenStageGroups(groups).map((r) => r.id)).toEqual(['root-1', 'child-1', 'root-2'])
  })
})

describe('moveItemRowToStage (spec 0172, out-of-scope: only a ROOT drags, its subtree comes along)', () => {
  it('moves a root and its whole subtree into another stage, retagging only the root', () => {
    const rows = [
      row({ id: 'root', stage_key: null }),
      row({ id: 'child', parent_key: 'root', stage_key: null }),
    ]
    const result = moveItemRowToStage(rows, [stage()], 'root', 'stage-1', 0)

    expect(result.map((r) => r.id)).toEqual(['root', 'child'])
    expect(result.find((r) => r.id === 'root')?.stage_key).toBe('stage-1')
    expect(result.find((r) => r.id === 'child')?.stage_key).toBeNull()
  })

  it('inserts the moved block before the target root at the given index, not splitting a sibling subtree', () => {
    const rows = [
      row({ id: 'a', stage_key: 'stage-1' }),
      row({ id: 'a-child', parent_key: 'a' }),
      row({ id: 'b', stage_key: 'stage-1' }),
      row({ id: 'moved', stage_key: null }),
    ]
    const result = moveItemRowToStage(rows, [stage()], 'moved', 'stage-1', 1)

    expect(result.map((r) => r.id)).toEqual(['a', 'a-child', 'moved', 'b'])
  })

  it('returns the SAME array reference when rowId is not a root (sub-items are not draggable)', () => {
    const rows = [row({ id: 'root' }), row({ id: 'child', parent_key: 'root' })]
    const result = moveItemRowToStage(rows, [stage()], 'child', 'stage-1', 0)

    expect(result).toBe(rows)
  })

  it('returns the SAME array reference for an unknown target container', () => {
    const rows = [row({ id: 'root' })]
    const result = moveItemRowToStage(rows, [stage()], 'root', 'not-a-container', 0)

    expect(result).toBe(rows)
  })
})
