import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** Mirrors the backend walk-depth guard: defends against a malformed/cyclic tree. */
const MAX_DEPTH = 100

/**
 * The nearest ancestor starting at `parentId` whose OWN `is_active` is false
 * (spec 0208 AC-022): the reason a category is effectively not active even
 * when its own flag is on. `null` when no ancestor is inactive, on a root, or
 * when the parent is not in the tree.
 */
export function findInactiveAncestor(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): ProductCategoryTreeNode | null {
  let node = parentId !== null ? nodesById.get(parentId) : undefined
  let depth = 0

  while (node !== undefined && depth < MAX_DEPTH) {
    if (!node.is_active) {
      return node
    }
    node = node.parent_id !== null ? nodesById.get(node.parent_id) : undefined
    depth += 1
  }

  return null
}
