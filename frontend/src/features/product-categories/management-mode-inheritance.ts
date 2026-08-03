import type {
  CategoryManagementMode,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'

/** Mirrors `CategoryManagementModeInheritance`'s own walk-depth guard (backend): defends against a malformed/cyclic tree. */
const MAX_DEPTH = 100

/** The management mode a candidate parent would impose on a category, plus the root it comes from. */
export interface InheritedManagementMode {
  managementMode: CategoryManagementMode
  sourceCategory: { id: number; name: string }
}

/**
 * Resolves the `management_mode` a category would inherit under `parentId`:
 * walks the cached tree up to the branch ROOT and returns the root's value.
 * Null when there is nothing to inherit (`parentId === null` — the category
 * is a root and owns the value — or the node is not in the tree).
 *
 * Client-side mirror of the backend's root walk (spec 0077); the write path
 * (ProductCategoryService, 422 on a divergent value) stays the authority.
 * Modeled 1:1 on `resolveInheritedQuoteFlag` (same inheritance pattern,
 * no new shape introduced).
 */
export function resolveInheritedManagementMode(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): InheritedManagementMode | null {
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

  return { managementMode: node.management_mode, sourceCategory: { id: node.id, name: node.name } }
}
