import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * One EDITOR row of the card field (user request 2026-11-18): a parent (root)
 * category with every category picked under it, instead of one row per
 * category. Editor-only: the wire keeps one `ProductLineRow` per category
 * (see {@link flattenProductLineGroups}), so the API contract is unchanged.
 */
export interface ProductLineGroup {
  rootCategoryId: number | null
  /** In the order the operator picked them; empty while the row is being filled. */
  categoryIds: number[]
}

/**
 * Folds the flat rows into one group per root, in order of first appearance
 * (the same order spec 0411 creates one request per root in). A row whose
 * root is not known (nothing picked, or the category tree not loaded yet)
 * stays a group of its own: it cannot be merged with anything.
 */
export function groupProductLineRows(
  rows: readonly ProductLineRow[],
  rootCategoryFor: (row: ProductLineRow) => number | null,
): ProductLineGroup[] {
  const groups: ProductLineGroup[] = []
  const groupByRoot = new Map<number, ProductLineGroup>()

  for (const row of rows) {
    const rootCategoryId = rootCategoryFor(row)
    const categoryId = row.product_category_id
    const existing = rootCategoryId === null ? undefined : groupByRoot.get(rootCategoryId)

    if (existing === undefined) {
      const group: ProductLineGroup = { rootCategoryId, categoryIds: categoryId === null ? [] : [categoryId] }
      groups.push(group)
      if (rootCategoryId !== null) {
        groupByRoot.set(rootCategoryId, group)
      }
    } else if (categoryId !== null && !existing.categoryIds.includes(categoryId)) {
      existing.categoryIds.push(categoryId)
    }
  }

  return groups
}

/**
 * Back to the wire shape: one row per picked category. A group with nothing
 * picked yet stays ONE incomplete row (category null), so the form's
 * "category required" rule still stops it, exactly as an unfinished row did.
 */
export function flattenProductLineGroups(groups: readonly ProductLineGroup[]): ProductLineRow[] {
  return groups.flatMap((group): ProductLineRow[] =>
    group.categoryIds.length === 0
      ? [{ root_category_id: group.rootCategoryId, product_category_id: null }]
      : group.categoryIds.map((categoryId) => ({
          root_category_id: group.rootCategoryId,
          product_category_id: categoryId,
        })),
  )
}
