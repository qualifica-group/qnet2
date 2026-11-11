import { resolveBranchRoot } from '@/features/product-categories/branch-root'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** The "several open opportunities" rule a candidate parent would impose on a category, plus the root it comes from. */
export interface InheritedMultipleOpenOpportunitiesFlag {
  multipleOpenOpportunities: boolean
  sourceCategory: { id: number; name: string }
}

/**
 * Resolves the `multiple_open_opportunities` flag a category would inherit
 * under `parentId`: the branch ROOT's value (`resolveBranchRoot`). Null when
 * there is nothing to inherit — the category is a root and owns the flag.
 */
export function resolveInheritedMultipleOpenOpportunitiesFlag(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): InheritedMultipleOpenOpportunitiesFlag | null {
  const root = resolveBranchRoot(nodesById, parentId)

  if (root === null) {
    return null
  }

  return {
    multipleOpenOpportunities: root.multiple_open_opportunities,
    sourceCategory: { id: root.id, name: root.name },
  }
}
