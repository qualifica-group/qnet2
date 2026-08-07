import { resolveBranchRoot } from '@/features/product-categories/branch-root'
import type {
  CategoryManagementMode,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'

/** The management mode a candidate parent would impose on a category, plus the root it comes from. */
export interface InheritedManagementMode {
  managementMode: CategoryManagementMode
  sourceCategory: { id: number; name: string }
}

/**
 * Resolves the `management_mode` a category would inherit under `parentId`:
 * the branch ROOT's value (`resolveBranchRoot`). Null when there is nothing to
 * inherit — the category is a root and owns the value (spec 0077).
 */
export function resolveInheritedManagementMode(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): InheritedManagementMode | null {
  const root = resolveBranchRoot(nodesById, parentId)

  if (root === null) {
    return null
  }

  return { managementMode: root.management_mode, sourceCategory: { id: root.id, name: root.name } }
}
