import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the product-categories for-select endpoint (spec 0023). */
export const PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE = 'product-categories'

/**
 * Every active category, containers included (spec 0223): the commission
 * configurator's picker, whose rule covers the whole branch.
 */
export const PRODUCT_CATEGORY_NODES_FOR_SELECT_RESOURCE = 'product-category-nodes'

/**
 * Fetches a page of product-category options from
 * `GET /api/product-categories/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `product-categories` resource. Distinct
 * from `fetchProductCategoryTree` (used by the category's own parent picker):
 * this is the flat, searchable list consumed by OTHER modules' relation
 * pickers (e.g. Projects/Campaigns). Items carry only `label` (name).
 */
export function fetchProductCategoriesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE, params)
}

interface UseProductCategoriesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a product-category single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `product-categories` resource.
 */
export function useProductCategoriesForSelect({
  search,
  ids,
  enabled,
}: UseProductCategoriesForSelectOptions) {
  return useForSelect({
    resource: PRODUCT_CATEGORIES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
