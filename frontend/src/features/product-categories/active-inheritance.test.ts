import { describe, expect, it } from 'vitest'
import { findInactiveAncestor } from '@/features/product-categories/active-inheritance'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

function node(id: number, parent_id: number | null, is_active: boolean): ProductCategoryTreeNode {
  return { id, name: `C${id}`, parent_id, is_active } as ProductCategoryTreeNode
}

describe('findInactiveAncestor (spec 0208 AC-022)', () => {
  const byId = new Map([
    [1, node(1, null, false)],
    [2, node(2, 1, true)],
    [3, node(3, 2, true)],
    [4, node(4, null, true)],
  ])

  it('returns the nearest inactive node walking up from the parent', () => {
    expect(findInactiveAncestor(byId, 3)?.id).toBe(1)
    expect(findInactiveAncestor(byId, 1)?.id).toBe(1)
  })

  it('returns null on a root, an unknown parent or an all-active chain', () => {
    expect(findInactiveAncestor(byId, null)).toBeNull()
    expect(findInactiveAncestor(byId, 99)).toBeNull()
    expect(findInactiveAncestor(byId, 4)).toBeNull()
  })
})
