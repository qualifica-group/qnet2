import { resolveBranchRoot } from '@/features/product-categories/branch-root'
import type { CategoryUsageArea, ProductCategoryTreeNode } from '@/features/product-categories/types'

/** The usage area a candidate parent would impose on a category, plus the root it comes from. */
export interface InheritedUsageArea {
  usageArea: CategoryUsageArea
  sourceCategory: { id: number; name: string }
}

/**
 * Resolves the `usage_area` a category would inherit under `parentId`: the
 * branch ROOT's value. Null when the category is a root and owns it (spec 0389).
 */
export function resolveInheritedUsageArea(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): InheritedUsageArea | null {
  const root = resolveBranchRoot(nodesById, parentId)

  if (root === null) {
    return null
  }

  return { usageArea: root.usage_area, sourceCategory: { id: root.id, name: root.name } }
}
