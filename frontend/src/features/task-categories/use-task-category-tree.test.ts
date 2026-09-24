import { describe, expect, it } from 'vitest'
import { collectDescendantIds, taskCategoryPathLabel } from '@/features/task-categories/use-task-category-tree'
import type { TaskCategoryTreeNode } from '@/features/task-categories/use-task-category-tree'

/**
 * Spec 0154 D-1: pure tree utilities built on the for-select catalog's own
 * `meta.parent_id`/`meta.depth` — no HTTP here, `use-task-category-tree.ts`'s
 * query is exercised indirectly through the components that consume it.
 */

function node(overrides: Partial<TaskCategoryTreeNode> = {}): TaskCategoryTreeNode {
  return { id: 1, name: 'Root', color: 'blue', icon: null, parentId: null, depth: 0, ...overrides }
}

const TREE: TaskCategoryTreeNode[] = [
  node({ id: 1, name: 'Commerciale', parentId: null, depth: 0 }),
  node({ id: 2, name: 'Follow-up', parentId: 1, depth: 1 }),
  node({ id: 3, name: 'Preventivo', parentId: 2, depth: 2 }),
  node({ id: 4, name: 'Tecnico', parentId: null, depth: 0 }),
]

describe('taskCategoryPathLabel', () => {
  it('joins every ancestor name from root to leaf', () => {
    expect(taskCategoryPathLabel(TREE, 3)).toBe('Commerciale / Follow-up / Preventivo')
  })

  it('returns just the name for a root category', () => {
    expect(taskCategoryPathLabel(TREE, 1)).toBe('Commerciale')
  })

  it('returns null for an id not present in the given nodes (graceful degradation)', () => {
    expect(taskCategoryPathLabel(TREE, 999)).toBeNull()
  })

  it('never loops forever on a cyclic parent chain (defensive, server forbids cycles)', () => {
    const cyclic: TaskCategoryTreeNode[] = [
      node({ id: 10, name: 'A', parentId: 11 }),
      node({ id: 11, name: 'B', parentId: 10 }),
    ]
    expect(taskCategoryPathLabel(cyclic, 10)).toBe('B / A')
  })
})

describe('collectDescendantIds', () => {
  it('collects every descendant, not just direct children', () => {
    expect(collectDescendantIds(TREE, 1)).toEqual(new Set([2, 3]))
  })

  it('returns an empty set for a leaf', () => {
    expect(collectDescendantIds(TREE, 3)).toEqual(new Set())
  })

  it('does not include the node itself', () => {
    expect(collectDescendantIds(TREE, 1).has(1)).toBe(false)
  })
})
