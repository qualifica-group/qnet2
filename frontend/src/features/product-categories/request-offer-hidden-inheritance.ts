import { resolveBranchRoot } from '@/features/product-categories/branch-root'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** The "hide the offer in Gestione Richieste" rule a candidate parent would impose on a category, plus the root it comes from. */
export interface InheritedRequestOfferHiddenFlag {
  requestOfferHidden: boolean
  sourceCategory: { id: number; name: string }
}

/**
 * Resolves the `request_offer_hidden` flag (spec 0414) a category would
 * inherit under `parentId`: the branch ROOT's value (`resolveBranchRoot`).
 * Null when there is nothing to inherit — the category is a root and owns the flag.
 */
export function resolveInheritedRequestOfferHiddenFlag(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): InheritedRequestOfferHiddenFlag | null {
  const root = resolveBranchRoot(nodesById, parentId)

  if (root === null) {
    return null
  }

  return {
    requestOfferHidden: root.request_offer_hidden,
    sourceCategory: { id: root.id, name: root.name },
  }
}
