import { fetchForSelect } from '@/features/for-select/api'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the products for-select endpoint. */
export const PRODUCTS_FOR_SELECT_RESOURCE = 'products'

/**
 * Fetches a page of product options from `GET /api/products/for-select`.
 * Thin wrapper over the generic for-select fetcher, bound to the `products`
 * resource. Items carry `label` (name) and `subtitle` (their category); pass
 * `params: { category_ids: [...] }` to scope the page to those categories —
 * how the "prodotti di interesse" picker stays aligned with the
 * opportunity's product lines.
 */
/**
 * The category a for-select product hangs from, read from its own `meta`
 * (spec 0075 AC-012). A reader rather than a competing item type: the
 * envelope is `ForSelectItem` for every consumer, and only the ones enforcing
 * the category coherence need this one key.
 */
export function productCategoryIdOf(item: ForSelectItem): number | null {
  const meta = (item as { meta?: { category_id?: unknown } }).meta

  return typeof meta?.category_id === 'number' ? meta.category_id : null
}

export function fetchProductsForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(PRODUCTS_FOR_SELECT_RESOURCE, params)
}
