import { describe, expect, it } from 'vitest'
import { resolveSimplifiedOfferLine } from '@/features/product-lines/category-tree-scope'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** Spec 0114: `resolveSimplifiedOfferLine` — the loosest covered category wins. */
function node(overrides: Partial<ProductCategoryTreeNode> & { id: number }): ProductCategoryTreeNode {
  return {
    name: 'Node',
    parent_id: null,
    children: [],
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    is_selectable: true,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    simplified_offer_line: false,
    ...overrides,
  }
}

const TREE: ProductCategoryTreeNode[] = [
  node({ id: 1, name: 'Training', simplified_offer_line: true }),
  node({ id: 2, name: 'Consulting', simplified_offer_line: false }),
]

describe('resolveSimplifiedOfferLine', () => {
  it('is false when no covered category is simplified', () => {
    expect(resolveSimplifiedOfferLine(TREE, [2])).toBe(false)
  })

  it('is true as soon as ONE covered category is simplified, even mixed with others', () => {
    expect(resolveSimplifiedOfferLine(TREE, [1])).toBe(true)
    expect(resolveSimplifiedOfferLine(TREE, [1, 2])).toBe(true)
  })

  it('is false for an empty or unresolved set of ids', () => {
    expect(resolveSimplifiedOfferLine(TREE, [])).toBe(false)
    expect(resolveSimplifiedOfferLine(TREE, [999])).toBe(false)
  })
})
