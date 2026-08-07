import { resolveBranchRoot } from '@/features/product-categories/branch-root'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** The quote flag a candidate parent would impose on a category, plus the root it comes from. */
export interface InheritedQuoteFlag {
  requiresQuote: boolean
  sourceCategory: { id: number; name: string }
}

/**
 * Resolves the `requires_quote` flag a category would inherit under
 * `parentId`: the branch ROOT's value (`resolveBranchRoot`). Null when there
 * is nothing to inherit — the category is a root and owns the flag.
 */
export function resolveInheritedQuoteFlag(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): InheritedQuoteFlag | null {
  const root = resolveBranchRoot(nodesById, parentId)

  if (root === null) {
    return null
  }

  return { requiresQuote: root.requires_quote, sourceCategory: { id: root.id, name: root.name } }
}
