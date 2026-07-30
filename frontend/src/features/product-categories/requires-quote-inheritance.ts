import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** Mirrors `RequiresQuoteInheritance`'s own walk-depth guard (backend): defends against a malformed/cyclic tree. */
const MAX_DEPTH = 100

/** The quote flag a candidate parent would impose on a category, plus the root it comes from. */
export interface InheritedQuoteFlag {
  requiresQuote: boolean
  sourceCategory: { id: number; name: string }
}

/**
 * Resolves the `requires_quote` flag a category would inherit under
 * `parentId`: walks the cached tree up to the branch ROOT and returns the
 * root's flag. Null when there is nothing to inherit (`parentId === null` —
 * the category is a root and owns the flag — or the node is not in the tree).
 *
 * Client-side mirror of the backend's root walk; the write path
 * (ProductCategoryService, 422 on a divergent value) stays the authority.
 */
export function resolveInheritedQuoteFlag(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): InheritedQuoteFlag | null {
  let node = parentId !== null ? nodesById.get(parentId) : undefined

  if (node === undefined) {
    return null
  }

  let depth = 0

  while (node.parent_id !== null && depth < MAX_DEPTH) {
    const parent = nodesById.get(node.parent_id)
    if (parent === undefined) {
      break
    }
    node = parent
    depth += 1
  }

  return { requiresQuote: node.requires_quote, sourceCategory: { id: node.id, name: node.name } }
}
