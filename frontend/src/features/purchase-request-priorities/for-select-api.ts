import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the purchase-request-priorities for-select endpoint. */
export const PURCHASE_REQUEST_PRIORITIES_FOR_SELECT_RESOURCE = 'purchase-request-priorities'

/**
 * The presentation bag this resource projects alongside `{id, label}`, so a
 * picker can draw the SAME badge as the grid without a second request.
 */
export interface PurchaseRequestPriorityForSelectMeta {
  /** Palette token of `BADGE_COLOR_TOKENS`, never a hex. */
  color: string
  /** Curated lucide name of `ICON_NAMES`, or null when unset. */
  icon: string | null
  /** Spec 0247 D-3: at most one row per catalog. */
  is_default: boolean
}

/** A single RDA priority option as returned by `GET /api/purchase-request-priorities/for-select`. */
export interface PurchaseRequestPriorityForSelectItem extends ForSelectItem {
  meta: PurchaseRequestPriorityForSelectMeta
}

/**
 * Fetches a page of RDA priority options from `GET /api/purchase-request-priorities/for-select`.
 * Reuses the generic fetcher and narrows its meta-less `ForSelectItem` to the
 * richer shape this endpoint actually returns — same approach as
 * `fetchStatusesForReorder`, so the request logic stays in one place.
 * Ungated by permission (ADR 0011): any authenticated actor can resolve
 * options. Inactive rows are omitted unless explicitly requested via `ids`
 * (edit-mode hydration).
 */
export async function fetchPurchaseRequestPrioritiesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<PurchaseRequestPriorityForSelectItem>> {
  const response = await fetchForSelect(PURCHASE_REQUEST_PRIORITIES_FOR_SELECT_RESOURCE, params)
  return { ...response, items: response.items as PurchaseRequestPriorityForSelectItem[] }
}

interface UsePurchaseRequestPrioritiesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding an RDA priority single-select: debounced server search,
 * offset pagination and `ids[]` hydration, bound to the `purchase-request-priorities` resource.
 */
export function usePurchaseRequestPrioritiesForSelect({ search, ids, enabled }: UsePurchaseRequestPrioritiesForSelectOptions) {
  return useForSelect({
    resource: PURCHASE_REQUEST_PRIORITIES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
