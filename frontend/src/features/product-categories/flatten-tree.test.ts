import { describe, expect, it } from 'vitest'
import {
  collectSelectableIds,
  flattenCategoryTree,
  pruneToPickable,
} from '@/features/product-categories/flatten-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/**
 * Spec 0074 D-6: the tree is the STRUCTURAL channel and stays complete, so the
 * destination picker built on it filters here. Hiding a container must never
 * hide the branch underneath it.
 */
function node(overrides: Partial<ProductCategoryTreeNode> & { id: number; name: string }): ProductCategoryTreeNode {
  return {
    parent_id: null,
    children: [],
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    is_selectable: true,
    management_mode: 'multiple',
    ...overrides,
  }
}

const TREE: ProductCategoryTreeNode[] = [
  node({
    id: 1,
    name: 'Container',
    is_selectable: false,
    children: [node({ id: 2, name: 'Leaf', parent_id: 1 })],
  }),
  node({ id: 3, name: 'Standalone' }),
]

describe('flattenCategoryTree', () => {
  it('lists every node when no filter is asked for (parent picker, bulk move — AC-017)', () => {
    expect(flattenCategoryTree(TREE).map((option) => option.id)).toEqual([1, 2, 3])
  })

  /**
   * User directive 2026-08-03: an unselectable node is no longer DROPPED but
   * listed disabled — it is the parent its pickable children hang from, and
   * hiding it left the picker reading as a set of unrelated leaves.
   */
  it('lists an unselectable node disabled, with its children still pickable (AC-015)', () => {
    const options = flattenCategoryTree(TREE, { pickableIds: collectSelectableIds(TREE) })

    expect(options.map((option) => option.id)).toEqual([1, 2, 3])
    expect(options[0].disabled).toBe(true)
    expect(options[1].disabled).toBeUndefined()
    // The child keeps the depth of its REAL tree position, which is what the
    // select indents by.
    expect(options[1]).toMatchObject({ name: 'Leaf', depth: 1 })
  })

  it('keeps an unselectable node pickable when its id is explicitly preserved (AC-016)', () => {
    const options = flattenCategoryTree(TREE, {
      pickableIds: collectSelectableIds(TREE),
      keepIds: [1],
    })

    expect(options.map((option) => option.disabled)).toEqual([undefined, undefined, undefined])
  })

  it('prunes a branch offering nothing pickable, keeping the ancestors of what is', () => {
    const pruned = pruneToPickable(TREE, new Set([2]))

    expect(pruned.map((node) => node.id)).toEqual([1])
    expect(pruned[0].children.map((node) => node.id)).toEqual([2])
  })
})
