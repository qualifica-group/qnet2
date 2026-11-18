import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { rootCategoryIdFor } from '@/features/product-lines/category-tree-scope'
import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * The names of the distinct parent categories (roots) the rows fall under, in
 * order of first appearance — the same grouping the server applies when it
 * creates one request per parent category (spec 0411, D-2). A row without a
 * category counts through its picked root, an empty row not at all.
 */
export function rootSplitNames(rows: ProductLineRow[], tree: ProductCategoryTreeNode[]): string[] {
  const rootIds: number[] = []

  for (const row of rows) {
    const rootId =
      row.root_category_id ?? (row.product_category_id !== null ? rootCategoryIdFor(tree, row.product_category_id) : null)
    if (rootId !== null && !rootIds.includes(rootId)) {
      rootIds.push(rootId)
    }
  }

  return rootIds.map((id) => tree.find((root) => root.id === id)?.name ?? String(id))
}
