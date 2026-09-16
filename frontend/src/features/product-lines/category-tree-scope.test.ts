import { describe, expect, it } from 'vitest'
import {
  pickableCategoryIdsFor,
  resolveSimplifiedOfferLine,
  rootCategoryIdFor,
  selectableIdsUnderRoot,
} from '@/features/product-lines/category-tree-scope'
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
    is_reportable: false,
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

/**
 * Spec 0129 D-6/D-7: `includeContainers` (the `ProductLinesField` competence
 * variant) admits a container category whose effective function matches, or a
 * functionless ("neutral") one with at least one descendant under that
 * function. Default (`includeContainers` omitted) is byte-for-byte the spec
 * 0111 D-4 behavior — offers/projects/campaigns/requests must see no change.
 */
describe('pickableCategoryIdsFor', () => {
  const FUNCTION_A = 1
  const FUNCTION_B = 2

  const CONTAINER_TREE: ProductCategoryTreeNode[] = [
    node({
      id: 100,
      name: 'Formazione',
      business_function_id: FUNCTION_A,
      is_selectable: false,
      children: [node({ id: 101, name: 'Corso base', parent_id: 100 })],
    }),
    node({
      id: 200,
      name: 'Neutral container',
      business_function_id: null,
      is_selectable: false,
      children: [
        node({ id: 201, name: 'Under A', parent_id: 200, business_function_id: FUNCTION_A }),
        node({ id: 202, name: 'Under B', parent_id: 200, business_function_id: FUNCTION_B }),
      ],
    }),
    node({
      id: 300,
      name: 'Leaf under A',
      business_function_id: FUNCTION_A,
    }),
  ]

  it('default: only is_selectable leaves matching the function (spec 0111 D-4, unchanged)', () => {
    const ids = pickableCategoryIdsFor(CONTAINER_TREE, FUNCTION_A)

    expect(ids.has(100)).toBe(false)
    expect(ids.has(101)).toBe(true)
    expect(ids.has(200)).toBe(false)
    expect(ids.has(201)).toBe(true)
    expect(ids.has(300)).toBe(true)
  })

  it('D-6: with includeContainers, a container whose own function matches is pickable', () => {
    const ids = pickableCategoryIdsFor(CONTAINER_TREE, FUNCTION_A, { includeContainers: true })

    expect(ids.has(100)).toBe(true)
    expect(ids.has(101)).toBe(true)
  })

  it('D-7: a functionless container is pickable for a function held by at least one descendant', () => {
    const ids = pickableCategoryIdsFor(CONTAINER_TREE, FUNCTION_A, { includeContainers: true })
    expect(ids.has(200)).toBe(true)

    const idsForB = pickableCategoryIdsFor(CONTAINER_TREE, FUNCTION_B, { includeContainers: true })
    expect(idsForB.has(200)).toBe(true)
  })

  it('D-7: a functionless container with no descendant under that function is not pickable', () => {
    const OTHER_FUNCTION = 3
    const ids = pickableCategoryIdsFor(CONTAINER_TREE, OTHER_FUNCTION, { includeContainers: true })
    expect(ids.has(200)).toBe(false)
  })
})

/**
 * Spec 0132 D-1/D-2: the CARD row's own scope — every `is_selectable`
 * descendant of the chosen ROOT, with no business-function constraint (the
 * function is derived server-side from whichever leaf is picked, never
 * filtered here).
 */
describe('selectableIdsUnderRoot', () => {
  const ROOT = 100
  const CONTAINER = 101
  const LEAF_1 = 102
  const LEAF_2 = 103
  const OTHER_ROOT = 200
  const OTHER_ROOT_LEAF = 201

  const TREE: ProductCategoryTreeNode[] = [
    node({
      id: ROOT,
      name: 'Root',
      is_selectable: false,
      children: [
        node({
          id: CONTAINER,
          name: 'Container',
          parent_id: ROOT,
          is_selectable: false,
          children: [node({ id: LEAF_1, name: 'Leaf 1', parent_id: CONTAINER })],
        }),
        node({ id: LEAF_2, name: 'Leaf 2', parent_id: ROOT }),
      ],
    }),
    node({ id: OTHER_ROOT, name: 'Other root', children: [node({ id: OTHER_ROOT_LEAF, name: 'Other leaf', parent_id: OTHER_ROOT })] }),
  ]

  it('collects every selectable descendant of the root, at any depth', () => {
    const ids = selectableIdsUnderRoot(TREE, ROOT)

    expect(ids.has(LEAF_1)).toBe(true)
    expect(ids.has(LEAF_2)).toBe(true)
  })

  it('excludes a non-selectable container and everything under a DIFFERENT root', () => {
    const ids = selectableIdsUnderRoot(TREE, ROOT)

    expect(ids.has(CONTAINER)).toBe(false)
    expect(ids.has(ROOT)).toBe(false)
    expect(ids.has(OTHER_ROOT_LEAF)).toBe(false)
  })

  it('resolves to an empty set for an id absent from the tree', () => {
    expect(selectableIdsUnderRoot(TREE, 999).size).toBe(0)
  })
})

/** Spec 0132 AC-017: walking the tree from a persisted category to its root ancestor, for the edit-load preselection. */
describe('rootCategoryIdFor', () => {
  const ROOT = 100
  const CONTAINER = 101
  const LEAF = 102
  const STANDALONE_ROOT = 200

  const TREE: ProductCategoryTreeNode[] = [
    node({
      id: ROOT,
      name: 'Root',
      is_selectable: false,
      children: [
        node({
          id: CONTAINER,
          name: 'Container',
          parent_id: ROOT,
          is_selectable: false,
          children: [node({ id: LEAF, name: 'Leaf', parent_id: CONTAINER })],
        }),
      ],
    }),
    node({ id: STANDALONE_ROOT, name: 'Standalone root' }),
  ]

  it('walks up to the root ancestor from a deeply nested category', () => {
    expect(rootCategoryIdFor(TREE, LEAF)).toBe(ROOT)
  })

  it('resolves a root category to itself (D-5)', () => {
    expect(rootCategoryIdFor(TREE, STANDALONE_ROOT)).toBe(STANDALONE_ROOT)
  })

  it('returns null for an id absent from the tree', () => {
    expect(rootCategoryIdFor(TREE, 999)).toBeNull()
  })
})
