/**
 * The card-level policy a product-line row set resolves to (spec 0077): the
 * branch root of its categories and that root's EFFECTIVE `management_mode`.
 * Both are read off the category TREE the row's picker already renders — see
 * `category-tree-scope.ts`, which owns the resolution.
 */
import type { CategoryManagementMode } from '@/features/product-categories/types'

/** The two spec-0077 keys describing a category's branch root policy. */
export interface CategoryManagementMeta {
  managementMode: CategoryManagementMode
  rootCategoryId: number
}
