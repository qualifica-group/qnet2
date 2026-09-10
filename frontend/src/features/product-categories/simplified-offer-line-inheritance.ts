import { resolveBranchRoot } from '@/features/product-categories/branch-root'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** The simplified-offer-line rule a candidate parent would impose on a category, plus the root it comes from. */
export interface InheritedSimplifiedOfferLineFlag {
  simplifiedOfferLine: boolean
  sourceCategory: { id: number; name: string }
}

/**
 * Resolves the `simplified_offer_line` flag a category would inherit under
 * `parentId`: the branch ROOT's value (`resolveBranchRoot`). Null when there
 * is nothing to inherit — the category is a root and owns the flag.
 */
export function resolveInheritedSimplifiedOfferLineFlag(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): InheritedSimplifiedOfferLineFlag | null {
  const root = resolveBranchRoot(nodesById, parentId)

  if (root === null) {
    return null
  }

  return {
    simplifiedOfferLine: root.simplified_offer_line,
    sourceCategory: { id: root.id, name: root.name },
  }
}
