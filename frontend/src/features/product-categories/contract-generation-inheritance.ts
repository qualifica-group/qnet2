import { resolveBranchRoot } from '@/features/product-categories/branch-root'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** The contract rule a candidate parent would impose on a category, plus the root it comes from. */
export interface InheritedContractGenerationFlag {
  generatesContract: boolean
  sourceCategory: { id: number; name: string }
}

/**
 * Resolves the `generates_contract` flag a category would inherit under
 * `parentId`: the branch ROOT's value (`resolveBranchRoot`). Null when there
 * is nothing to inherit — the category is a root and owns the flag.
 */
export function resolveInheritedContractGenerationFlag(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): InheritedContractGenerationFlag | null {
  const root = resolveBranchRoot(nodesById, parentId)

  if (root === null) {
    return null
  }

  return {
    generatesContract: root.generates_contract,
    sourceCategory: { id: root.id, name: root.name },
  }
}
