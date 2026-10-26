import { describe, expect, it } from 'vitest'
import { findInactiveAncestor, findSectorLabel, flattenSectorTree } from '@/features/sectors/flatten-tree'
import type { SectorTreeNode } from '@/features/sectors/types'

function node(id: number, parentId: number | null, isActive: boolean, children: SectorTreeNode[] = []): SectorTreeNode {
  return { id, code: null, name: `S${id}`, parent_id: parentId, is_active: isActive, children }
}

/** 1 (inactive) > 2 > 3 (inactive) > 4, plus an active root 5. */
const TREE: SectorTreeNode[] = [
  node(1, null, false, [node(2, 1, true, [node(3, 2, false, [node(4, 3, true)])])]),
  node(5, null, true),
]

describe('findInactiveAncestor (spec 0212)', () => {
  it('returns the nearest inactive node on the path to the parent, inclusive', () => {
    expect(findInactiveAncestor(TREE, 4)?.id).toBe(3)
    expect(findInactiveAncestor(TREE, 2)?.id).toBe(1)
    expect(findInactiveAncestor(TREE, 1)?.id).toBe(1)
  })

  it('returns null for a root, an active path or an unknown parent', () => {
    expect(findInactiveAncestor(TREE, null)).toBeNull()
    expect(findInactiveAncestor(TREE, 5)).toBeNull()
    expect(findInactiveAncestor(TREE, 99)).toBeNull()
  })
})

describe('sector labels in the parent picker (spec 0213)', () => {
  const CODED: SectorTreeNode[] = [
    { id: 1, code: '07', name: 'Carta', parent_id: null, is_active: true, children: [node(2, 1, true)] },
  ]

  it('labels a coded node "code — name" and an uncoded one by its name, keeping the indentation', () => {
    expect(flattenSectorTree(CODED).map((option) => option.name)).toEqual(['07 — Carta', '    ↳ S2'])
  })

  it('finds the label of a node anywhere in the tree', () => {
    expect(findSectorLabel(CODED, 1)).toBe('07 — Carta')
    expect(findSectorLabel(CODED, 2)).toBe('S2')
    expect(findSectorLabel(CODED, 99)).toBeNull()
  })
})
