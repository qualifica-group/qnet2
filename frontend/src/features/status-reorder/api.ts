import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { fetchForSelect } from '@/features/for-select/api'
import type { ForSelectItem } from '@/features/for-select/types'
import type {
  ReorderedStatusEntry,
  StatusReorderItem,
  SystemStatusKey,
} from '@/features/status-reorder/types'

/** Page size for the one-shot reads below: statuses are a small lookup table, never paginated. */
const STATUS_PAGE_SIZE = 100

/**
 * A for-select item extended with the markers this sheet needs (spec 0039,
 * widened by spec 0101), not modeled on the generic `ForSelectItem`. Both are
 * optional: a resource that projects neither yields `systemKey: null`
 * (unpinned) and `isActive: undefined` (unmarked).
 */
interface StatusForSelectItem extends ForSelectItem {
  meta?: { system_key?: SystemStatusKey; is_active?: boolean }
}

/**
 * Persists a new custom-status order (spec 0039 D-5). `resource` is either
 * `pipeline-statuses` or `opportunity-statuses`; `orderedIds` must be EXACTLY the
 * custom (non-system) ids, in their new visual order — the backend rejects
 * anything else with 422. Returns the fresh, full ordered list.
 */
export async function reorderStatuses(
  resource: string,
  orderedIds: number[],
): Promise<ReorderedStatusEntry[]> {
  const { data } = await apiClient.post<ApiResponse<ReorderedStatusEntry[]>>(
    `/${resource}/reorder`,
    { ordered_ids: orderedIds },
  )
  return data.data
}

/**
 * Fetches the full ordered status list (system + custom) used to seed the
 * reorder sheet. Statuses are always ordered `sort_order,name,id` server-side,
 * so a single page covers the complete set — no pagination needed for this
 * one-shot read. INACTIVE rows are included: see `include_inactive` below.
 */
export async function fetchStatusesForReorder(resource: string): Promise<StatusReorderItem[]> {
  const response = await fetchForSelect(resource, {
    limit: STATUS_PAGE_SIZE,
    // The reorder endpoints validate `ordered_ids` against the FULL set of
    // reorderable rows (`StatusOrderManager`: every custom row;
    // `LookupOrderManager`: every row), while a for-select answers with the
    // ACTIVE ones only. Without this flag a single deactivated row makes the
    // sheet send an incomplete set, and every drag 422s "none missing" —
    // permanently, until an admin reactivates it (spec 0101 AC-047/AC-049).
    // Additive and default-off server-side: a resource that does not declare
    // it in `rules()` drops it in `validated()`, so the other modules reusing
    // this sheet are unaffected. Sent as `1` because `ForSelectParams.params`
    // carries no boolean; Laravel's `boolean` rule accepts it.
    params: { include_inactive: 1 },
  })
  const items = response.items as StatusForSelectItem[]
  return items.map((item) => ({
    id: item.id,
    name: item.label,
    systemKey: item.meta?.system_key ?? null,
    isActive: item.meta?.is_active,
  }))
}

/**
 * Resolves the id of a resource's system status (spec 0039 D-3), e.g. the
 * "Nuovo" status used to preselect it on create. "Nuovo" always sorts first
 * (`sort_order = 0`), so the first page is enough.
 */
export async function fetchSystemStatusId(
  resource: string,
  key: 'new' | 'closed',
): Promise<number | null> {
  const response = await fetchForSelect(resource, { limit: STATUS_PAGE_SIZE })
  const items = response.items as StatusForSelectItem[]
  return items.find((item) => item.meta?.system_key === key)?.id ?? null
}
