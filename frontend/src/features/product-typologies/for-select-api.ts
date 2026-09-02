import { useQuery } from '@tanstack/react-query'
import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the product-typologies for-select endpoint. */
export const PRODUCT_TYPOLOGIES_FOR_SELECT_RESOURCE = 'product-typologies'

/**
 * Fetches a page of product typology options from
 * `GET /api/product-typologies/for-select`. Thin wrapper over the generic
 * for-select fetcher, bound to the `product-typologies` resource.
 */
export function fetchProductTypologiesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(PRODUCT_TYPOLOGIES_FOR_SELECT_RESOURCE, params)
}

interface UseProductTypologiesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a product typology single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `product-typologies` resource.
 */
export function useProductTypologiesForSelect({ search, ids, enabled }: UseProductTypologiesForSelectOptions) {
  return useForSelect({
    resource: PRODUCT_TYPOLOGIES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}

/**
 * The maximum page the for-select endpoint serves. A configuration lookup of
 * this kind holds units of rows, so one page is the whole catalogue (D-7: the
 * offer summary lists every typology, unpaginated).
 */
const ALL_TYPOLOGIES_LIMIT = 100

/** How long the typology catalogue stays fresh; it changes only when someone edits the module. */
const ALL_TYPOLOGIES_STALE_MS = 5 * 60 * 1000

/** Hoisted so a pending/failed query returns a stable reference (no re-render churn). */
const NO_TYPOLOGIES: ForSelectItem[] = []

/**
 * The FULL configured typology catalogue, as the Offer summary needs it
 * (spec 0099, D-7): every typology, so the per-typology block can list the
 * ones with no line in this offer at 0,00 (AC-051/AC-061). A plain, cached
 * `useQuery` rather than `useProductTypologiesForSelect` above: that one is
 * an infinite/searchable query built for a picker, while this consumer wants
 * one flat, complete, search-less list.
 */
export function useAllProductTypologies(): ForSelectItem[] {
  const { data } = useQuery({
    queryKey: [PRODUCT_TYPOLOGIES_FOR_SELECT_RESOURCE, 'all'],
    queryFn: () => fetchProductTypologiesForSelect({ offset: 0, limit: ALL_TYPOLOGIES_LIMIT }),
    staleTime: ALL_TYPOLOGIES_STALE_MS,
  })

  return data?.items ?? NO_TYPOLOGIES
}
