import { resolveBranchRoot } from '@/features/product-categories/branch-root'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** The multi-offer work order rule a candidate parent would impose on a category, plus the root it comes from. */
export interface InheritedMultiQuoteWorkOrderFlag {
  multiQuoteWorkOrder: boolean
  sourceCategory: { id: number; name: string }
}

/**
 * Resolves the `multi_quote_work_order` flag a category would inherit under
 * `parentId`: the branch ROOT's value (`resolveBranchRoot`). Null when there
 * is nothing to inherit — the category is a root and owns the flag.
 */
export function resolveInheritedMultiQuoteWorkOrderFlag(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): InheritedMultiQuoteWorkOrderFlag | null {
  const root = resolveBranchRoot(nodesById, parentId)

  if (root === null) {
    return null
  }

  return {
    multiQuoteWorkOrder: root.multi_quote_work_order,
    sourceCategory: { id: root.id, name: root.name },
  }
}
