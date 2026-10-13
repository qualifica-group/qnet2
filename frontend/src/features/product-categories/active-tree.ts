import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

function hasInactiveNode(nodes: ProductCategoryTreeNode[]): boolean {
  return nodes.some((node) => !node.is_active || hasInactiveNode(node.children))
}

/** Every id on the path root -> `keepIds` target, targets included. */
function collectKeepPath(nodes: ProductCategoryTreeNode[], keepIds: ReadonlySet<number>): Set<number> {
  const path = new Set<number>()

  function visit(node: ProductCategoryTreeNode): boolean {
    let onPath = keepIds.has(node.id)
    for (const child of node.children) {
      if (visit(child)) {
        onPath = true
      }
    }
    if (onPath) {
      path.add(node.id)
    }
    return onPath
  }

  nodes.forEach(visit)
  return path
}

/**
 * Prunes the category tree for a DESTINATION picker (spec 0208 D-1/D-8): a
 * non-active node disappears with its whole subtree, because an inactive
 * category makes its branch inactive. The exception is history (D-2): the
 * path to a `keepIds` target (the value already saved) survives so the saved
 * value is still listed. A node kept only as path inside an inactive branch
 * becomes `is_selectable: false` (context, never a new choice); a `keepIds`
 * node keeps its own flags.
 *
 * The tree stays complete server-side; structural consumers (parent picker,
 * bulk-move) and rule resolvers must NOT prune. Returns the input reference
 * when no node is inactive, so memoised callers stay stable.
 */
export function activeCategoryTree(
  nodes: ProductCategoryTreeNode[],
  keepIds: readonly number[],
): ProductCategoryTreeNode[] {
  if (!hasInactiveNode(nodes)) {
    return nodes
  }

  const keepSet = new Set(keepIds)
  const path = collectKeepPath(nodes, keepSet)

  function prune(candidates: ProductCategoryTreeNode[], branchInactive: boolean): ProductCategoryTreeNode[] {
    return candidates.flatMap((node) => {
      const inactive = branchInactive || !node.is_active
      if (inactive && !path.has(node.id)) {
        return []
      }
      const children = prune(node.children, inactive)
      const context = inactive && !keepSet.has(node.id)
      return [{ ...node, children, ...(context ? { is_selectable: false } : {}) }]
    })
  }

  return prune(nodes, false)
}
