import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { rootCategoryIdFor } from '@/features/product-lines/category-tree-scope'

/** Shown while the category tree resolves: never the bare id. */
const PENDING_LABEL = '…'

/** Depth-first lookup by id in the cached category tree. */
function findCategory(nodes: ProductCategoryTreeNode[], id: number): ProductCategoryTreeNode | null {
  for (const node of nodes) {
    if (node.id === id) {
      return node
    }
    const found = findCategory(node.children, id)
    if (found) {
      return found
    }
  }
  return null
}

/**
 * "Root > Category" of a draft classification row, as the details'
 * read-only lists name it — for a create draft, whose rows hold ids only.
 */
export function categoryPathLabel(tree: ProductCategoryTreeNode[], categoryId: number): string {
  const category = findCategory(tree, categoryId)
  if (!category) {
    return PENDING_LABEL
  }
  const rootId = rootCategoryIdFor(tree, categoryId)
  const root = rootId !== null && rootId !== categoryId ? findCategory(tree, rootId) : null
  return root ? `${root.name} > ${category.name}` : category.name
}
