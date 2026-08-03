import { describe, expect, it } from 'vitest'
import { flattenCategoryTree } from '@/features/product-categories/flatten-tree'
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

  it('drops unselectable nodes but keeps their children (AC-015)', () => {
    const options = flattenCategoryTree(TREE, { selectableOnly: true })

    expect(options.map((option) => option.id)).toEqual([2, 3])
    // The surviving child keeps the indent of its REAL depth, not of the
    // filtered list.
    expect(options[0].name).toContain('↳ Leaf')
  })

  it('keeps an unselectable node listed when its id is explicitly preserved (AC-016)', () => {
    const options = flattenCategoryTree(TREE, { selectableOnly: true, keepIds: [1] })

    expect(options.map((option) => option.id)).toEqual([1, 2, 3])
  })
})
