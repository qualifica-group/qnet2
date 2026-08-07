import { resolveBranchRoot } from '@/features/product-categories/branch-root'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** The single-offer rule a candidate parent would impose on a category, plus the root it comes from. */
export interface InheritedSingleQuoteFlag {
  singleQuotePerOpportunity: boolean
  sourceCategory: { id: number; name: string }
}

/**
 * Resolves the `single_quote_per_opportunity` flag a category would inherit
 * under `parentId`: the branch ROOT's value (`resolveBranchRoot`). Null when
 * there is nothing to inherit — the category is a root and owns the flag.
 */
export function resolveInheritedSingleQuoteFlag(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): InheritedSingleQuoteFlag | null {
  const root = resolveBranchRoot(nodesById, parentId)

  if (root === null) {
    return null
  }

  return {
    singleQuotePerOpportunity: root.single_quote_per_opportunity,
    sourceCategory: { id: root.id, name: root.name },
  }
}
