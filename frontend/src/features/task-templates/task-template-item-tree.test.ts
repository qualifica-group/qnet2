import { describe, expect, it } from 'vitest'
import {
  FORM_ROW_TREE_ACCESSORS,
  MAX_ITEM_DEPTH,
  PERSISTED_ITEM_TREE_ACCESSORS,
  canAddSubtaskAtDepth,
  computeItemDepths,
  getSubtreeIds,
} from '@/features/task-templates/task-template-item-tree'
import type { TaskTemplateItem, TaskTemplateItemFormRow } from '@/features/task-templates/types'

/** Spec 0172 D-1: builds a depth-first chain root -> child -> grandchild -> great-grandchild, one row per level. */
function formRowChain(depth: number): TaskTemplateItemFormRow[] {
  const rows: TaskTemplateItemFormRow[] = []
  for (let level = 0; level <= depth; level += 1) {
    rows.push({
      id: `row-${level}`,
      title: `Level ${level}`,
      description: null,
      estimated_minutes: null,
      task_status_id: null,
      due_offset_days: 0,
      stage_key: null,
      parent_key: level === 0 ? null : `row-${level - 1}`,
    })
  }
  return rows
}

describe('computeItemDepths (spec 0172 D-1)', () => {
  it('assigns depth 0 to every root, and increments per ancestor', () => {
    const rows = formRowChain(3)
    const depths = computeItemDepths(rows, FORM_ROW_TREE_ACCESSORS)

    expect(depths.get('row-0')).toBe(0)
    expect(depths.get('row-1')).toBe(1)
    expect(depths.get('row-2')).toBe(2)
    expect(depths.get('row-3')).toBe(3)
  })

  it('treats siblings under the same parent independently', () => {
    const rows: TaskTemplateItemFormRow[] = [
      { id: 'root', title: 'Root', description: null, estimated_minutes: null, task_status_id: null, due_offset_days: 0, stage_key: null, parent_key: null },
      { id: 'child-a', title: 'A', description: null, estimated_minutes: null, task_status_id: null, due_offset_days: 0, stage_key: null, parent_key: 'root' },
      { id: 'child-b', title: 'B', description: null, estimated_minutes: null, task_status_id: null, due_offset_days: 0, stage_key: null, parent_key: 'root' },
    ]
    const depths = computeItemDepths(rows, FORM_ROW_TREE_ACCESSORS)

    expect(depths.get('child-a')).toBe(1)
    expect(depths.get('child-b')).toBe(1)
  })

  it('works the same way over persisted items via numeric parent_id', () => {
    const items: TaskTemplateItem[] = [
      { id: 1, title: 'Root', description: null, estimated_minutes: null, task_status_id: null, task_status: null, due_offset_days: 0, sort_order: 0, task_template_stage_id: null, parent_id: null, attachments: [] },
      { id: 2, title: 'Child', description: null, estimated_minutes: null, task_status_id: null, task_status: null, due_offset_days: 0, sort_order: 1, task_template_stage_id: null, parent_id: 1, attachments: [] },
    ]
    const depths = computeItemDepths(items, PERSISTED_ITEM_TREE_ACCESSORS)

    expect(depths.get('1')).toBe(0)
    expect(depths.get('2')).toBe(1)
  })
})

describe('canAddSubtaskAtDepth (spec 0172 AC-018)', () => {
  it('allows adding a subtask below the cap', () => {
    expect(canAddSubtaskAtDepth(0)).toBe(true)
    expect(canAddSubtaskAtDepth(MAX_ITEM_DEPTH - 1)).toBe(true)
  })

  it('hides the action at the deepest allowed level (a child would exceed the cap)', () => {
    expect(canAddSubtaskAtDepth(MAX_ITEM_DEPTH)).toBe(false)
  })
})

describe('getSubtreeIds (spec 0172 AC-018: removing a row removes its whole subtree)', () => {
  it('returns the root id alone when it has no children', () => {
    const rows = formRowChain(0)
    expect(getSubtreeIds(rows, FORM_ROW_TREE_ACCESSORS, 'row-0')).toEqual(['row-0'])
  })

  it('returns the full depth-first chain for a linear subtree', () => {
    const rows = formRowChain(3)
    expect(getSubtreeIds(rows, FORM_ROW_TREE_ACCESSORS, 'row-0')).toEqual(['row-0', 'row-1', 'row-2', 'row-3'])
  })

  it('returns only the id itself for a leaf row somewhere in the middle of the tree', () => {
    const rows = formRowChain(3)
    expect(getSubtreeIds(rows, FORM_ROW_TREE_ACCESSORS, 'row-2')).toEqual(['row-2', 'row-3'])
  })

  it('collects a branching subtree depth-first, siblings in insertion order', () => {
    const rows: TaskTemplateItemFormRow[] = [
      { id: 'root', title: 'Root', description: null, estimated_minutes: null, task_status_id: null, due_offset_days: 0, stage_key: null, parent_key: null },
      { id: 'child-a', title: 'A', description: null, estimated_minutes: null, task_status_id: null, due_offset_days: 0, stage_key: null, parent_key: 'root' },
      { id: 'grandchild-a1', title: 'A1', description: null, estimated_minutes: null, task_status_id: null, due_offset_days: 0, stage_key: null, parent_key: 'child-a' },
      { id: 'child-b', title: 'B', description: null, estimated_minutes: null, task_status_id: null, due_offset_days: 0, stage_key: null, parent_key: 'root' },
    ]

    expect(getSubtreeIds(rows, FORM_ROW_TREE_ACCESSORS, 'root')).toEqual([
      'root',
      'child-a',
      'grandchild-a1',
      'child-b',
    ])
  })
})
