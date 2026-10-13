import { describe, expect, it } from 'vitest'
import { activeCategoryTree } from '@/features/product-categories/active-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

function node(
  id: number,
  overrides: Partial<ProductCategoryTreeNode> = {},
  children: ProductCategoryTreeNode[] = [],
): ProductCategoryTreeNode {
  return {
    id,
    name: `Category ${id}`,
    parent_id: null,
    children,
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    is_selectable: true,
    is_active: true,
    is_reportable: null,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: false,
    simplified_offer_line: false,
    simplified_offer_line_override: null,
    ...overrides,
  }
}

const ids = (nodes: ProductCategoryTreeNode[]): number[] =>
  nodes.flatMap((entry) => [entry.id, ...ids(entry.children)])

describe('activeCategoryTree (spec 0208 AC-018)', () => {
  it('returns the same reference when nothing is inactive', () => {
    const tree = [node(1, {}, [node(2, { parent_id: 1 })])]

    expect(activeCategoryTree(tree, [])).toBe(tree)
    expect(activeCategoryTree(tree, [2])).toBe(tree)
  })

  it('drops an inactive node with its whole subtree, even active descendants', () => {
    const tree = [
      node(1, {}, [
        node(2, { parent_id: 1, is_active: false }, [node(3, { parent_id: 2 })]),
        node(4, { parent_id: 1 }),
      ]),
    ]

    expect(ids(activeCategoryTree(tree, []))).toEqual([1, 4])
  })

  it('keeps the path to a saved value, inactive ancestors becoming non-selectable context', () => {
    const tree = [
      node(1, { is_active: false }, [
        node(2, { parent_id: 1 }, [node(3, { parent_id: 2 })]),
        node(4, { parent_id: 1 }),
      ]),
    ]

    const result = activeCategoryTree(tree, [3])

    expect(ids(result)).toEqual([1, 2, 3])
    expect(result[0].is_selectable).toBe(false)
    expect(result[0].children[0].is_selectable).toBe(false)
    expect(result[0].children[0].children[0].is_selectable).toBe(true)
  })

  it('leaves an inactive saved node with its own flags and drops its other children', () => {
    const tree = [node(1, { is_active: false }, [node(2, { parent_id: 1 })])]

    const result = activeCategoryTree(tree, [1])

    expect(ids(result)).toEqual([1])
    expect(result[0].is_selectable).toBe(true)
  })
})
