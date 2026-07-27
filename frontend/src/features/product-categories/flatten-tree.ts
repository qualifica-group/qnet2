import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** A flattened tree node, ready to feed a plain `id`/`name` select. */
export interface FlatCategoryOption {
  id: number
  name: string
}

/**
 * Sentinel id standing for "no parent" in a category picker: `SearchableSelect`
 * models a selection as a number, so the root option needs a value no real
 * category can have. Shared by the category form and the bulk-move dialog.
 */
export const ROOT_PARENT_VALUE = 0

/** Indentation glyph prepended per depth level, so hierarchy reads at a glance in a flat list. */
const DEPTH_PREFIX = '    '

/**
 * Flattens the category tree into a depth-first, indented option list for the
 * product form's category picker (`SearchableSelect`): no dedicated
 * `for-select` endpoint exists for categories (spec 0017 scope), so the
 * already-fetched tree is reused client-side instead of a new backend call.
 */
export function flattenCategoryTree(
  nodes: ProductCategoryTreeNode[],
  depth = 0,
): FlatCategoryOption[] {
  return nodes.flatMap((node) => [
    { id: node.id, name: `${DEPTH_PREFIX.repeat(depth)}${depth > 0 ? '↳ ' : ''}${node.name}` },
    ...flattenCategoryTree(node.children, depth + 1),
  ])
}

/** Collects every descendant id of `nodeId` (inclusive), used to forbid picking a cyclic parent client-side. */
export function collectSubtreeIds(nodes: ProductCategoryTreeNode[], nodeId: number): Set<number> {
  const ids = new Set<number>()

  function visit(candidates: ProductCategoryTreeNode[], collecting: boolean) {
    for (const node of candidates) {
      const isTarget = collecting || node.id === nodeId
      if (isTarget) {
        ids.add(node.id)
      }
      visit(node.children, isTarget)
    }
  }

  visit(nodes, false)
  return ids
}
