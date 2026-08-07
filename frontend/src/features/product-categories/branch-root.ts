import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** Mirrors the backend `RootOwnedCategorySetting` walk-depth guard: defends against a malformed/cyclic tree. */
const MAX_DEPTH = 100

/**
 * The branch ROOT a category placed under `parentId` would belong to, walked
 * off the cached tree. `null` when there is nothing to inherit: `parentId` is
 * null (the category is itself a root and owns every root-owned setting) or
 * the node is not in the tree.
 *
 * Every root-owned setting (`requires_quote`, `management_mode`,
 * `single_quote_per_opportunity`) reads its inherited value off this ONE walk
 * — the tree already carries each setting EFFECTIVE on every node, so the
 * root's value is the inherited value. Client-side mirror of the backend
 * walk; the write path (ProductCategoryService, 422 on a divergent value)
 * stays the authority.
 */
export function resolveBranchRoot(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): ProductCategoryTreeNode | null {
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

  return node
}
