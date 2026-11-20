import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { rootCategoryIdFor } from '@/features/product-lines/category-tree-scope'
import type { ProductLineRow } from '@/features/product-lines/types'

/** One parent category (root) the product lines fall under: one request each (spec 0411). */
export interface RootSplitGroup {
  id: number
  name: string
}

/**
 * The distinct parent categories (roots) the rows fall under, in order of
 * first appearance — the same grouping the server applies when it creates one
 * request per parent category (spec 0411, D-2), and the order of its
 * `created_quote_ids`. A row without a category counts through its picked
 * root, an empty row not at all.
 */
export function rootSplitGroups(rows: ProductLineRow[], tree: ProductCategoryTreeNode[]): RootSplitGroup[] {
  const rootIds: number[] = []

  for (const row of rows) {
    const rootId =
      row.root_category_id ?? (row.product_category_id !== null ? rootCategoryIdFor(tree, row.product_category_id) : null)
    if (rootId !== null && !rootIds.includes(rootId)) {
      rootIds.push(rootId)
    }
  }

  return rootIds.map((id) => ({ id, name: tree.find((root) => root.id === id)?.name ?? String(id) }))
}

/** The names of `rootSplitGroups`, for the split notice. */
export function rootSplitNames(rows: ProductLineRow[], tree: ProductCategoryTreeNode[]): string[] {
  return rootSplitGroups(rows, tree).map((group) => group.name)
}
