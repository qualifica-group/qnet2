/**
 * Resolves a row set's management mode from the `meta` of whichever picked
 * category is already known (spec 0077 data_contract, `for-select` item):
 * `meta.management_mode` (effective/inherited value) and
 * `meta.root_category_id`, read the same way `productCategoryIdOf` reads a
 * product's category (`features/products/for-select-api.ts`). No extra
 * request: the meta arrives with the item `AsyncPaginatedSelect` already
 * exposes via `onItemChange`.
 *
 * D-5 grandfathering, mirrored client-side: a row hydrated from `knownLines`
 * (edit load) carries no `meta` until its category is re-picked in this
 * session, so a historic record's mode stays indeterminate (current, pre-0077
 * behaviour) until the user actually touches that row.
 */
import type { ForSelectItem } from '@/features/for-select/types'
import type { CategoryManagementMode } from '@/features/product-categories/types'
import type { ProductLineRow } from '@/features/product-lines/types'

/** The two spec-0077 `meta` keys carried by a product-category for-select item. */
export interface CategoryManagementMeta {
  managementMode: CategoryManagementMode
  rootCategoryId: number
}

/** Lookup of a picked category's resolved meta, keyed by `product_category_id`. */
export type CategoryMetaById = Record<number, CategoryManagementMeta>

/** Reads `meta.management_mode` / `meta.root_category_id` off a for-select item; `null` when either is absent or malformed. */
export function categoryManagementMetaOf(item: ForSelectItem | null): CategoryManagementMeta | null {
  if (item === null) {
    return null
  }
  const meta = (item as { meta?: { management_mode?: unknown; root_category_id?: unknown } }).meta
  const managementMode = meta?.management_mode
  const rootCategoryId = meta?.root_category_id

  if (managementMode !== 'single' && managementMode !== 'multiple') {
    return null
  }
  if (typeof rootCategoryId !== 'number') {
    return null
  }

  return { managementMode, rootCategoryId }
}

/**
 * The row set's resolved mode: the meta of the first row (in order) whose
 * category is present in `metaById`. `null` when no row's category is known
 * yet — the indeterminate case (point 4): current behaviour, unconstrained.
 */
export function resolveRowSetManagementMode(
  rows: ProductLineRow[],
  metaById: CategoryMetaById,
): CategoryManagementMeta | null {
  for (const row of rows) {
    if (row.product_category_id !== null) {
      const meta = metaById[row.product_category_id]
      if (meta) {
        return meta
      }
    }
  }
  return null
}
