/**
 * Row-level request/response types for the generic table framework: SSRM rows
 * payload/response, Set Filter values, bulk-delete and saved filter views.
 * Split out of `types.ts` purely to keep that file under the engineering.md
 * §6 size budget — every existing consumer keeps importing from
 * `@/features/table/types`, which re-exports this module verbatim.
 */
import type { AdvancedFilterValues } from '@/features/table/advanced-filters/types'
import type { TableRow } from '@/features/table/types'

/** Sort item as serialized in the SSRM request body. */
export interface SsrmSortModelItem {
  colId: string
  sort: 'asc' | 'desc'
}

/**
 * Row-set scope narrowing a domain's rows/values/export requests to one
 * parent record (spec 0067 D-1, e.g. an Opportunity's Quotes panel). Distinct
 * from `TableConfigScope` (`use-table-config.ts`): that one selects a config
 * SHAPE and enters the config's query key; this one selects a ROW SET and
 * never enters any query key, because the config is identical scoped or not.
 */
export interface TableRowScope {
  opportunityId?: number
  /**
   * Row-set scope to one Contract's Offerta (spec 0095 D-8, the Contract
   * detail's Commesse tab): Contratto/Offerta are 1:1, so filtering on
   * `work_orders.quote_id` is a direct `where`, no join. A no-op for every
   * domain but `work-orders`.
   */
  quoteId?: number
}

/** SSRM rows request payload (AG Grid IServerSideGetRowsRequest subset). */
export interface TableRowsPayload {
  startRow: number
  endRow: number
  sortModel: SsrmSortModelItem[]
  filterModel: Record<string, unknown>
  /**
   * Global quick-search term (spec 0009). Applied server-side as a bound
   * OR-LIKE over the domain's `searchable` columns; omitted/empty ⇒ no search.
   */
  search?: string
  /**
   * Applied advanced filters (spec 0032), combined in AND with `filterModel`
   * and `search`. Omitted/empty ⇒ no advanced filter restricts the query.
   */
  advancedFilters?: AdvancedFilterValues
  /**
   * The selected Product Category tab (spec 0064, request-management only):
   * extends the row query with an EXISTS on that category's product lines and
   * widens the `sortModel`/`filterModel` colId allow-list to that category's
   * `attr.<code>` columns. Omitted/null ⇒ today's behavior (the "Tutte" tab),
   * and any `attr.*` colId/filter key is rejected server-side.
   */
  productCategoryId?: number | null
  /**
   * Row-set scope to one parent record (spec 0067 D-1, `TableRowScope`), e.g.
   * an Opportunity's Quotes panel. Sent only when the caller passes a
   * `rowScope` with a value; a no-op for every domain but `quotes`.
   */
  opportunityId?: number | null
  /** Row-set scope to one Contract's Offerta (spec 0095 D-8), same rule as above; a no-op for every domain but `work-orders`. */
  quoteId?: number | null
  /**
   * Server-side tree data (spec 0157 D-1): `true` requests the domain's tree
   * shape instead of the flat one. Omitted/`false` ⇒ today's flat behavior,
   * unchanged for every domain but `tasks`.
   */
  tree?: boolean
  /**
   * The expanded node's id, present only when `tree` is true AND the request
   * is for a level BELOW the root (AG Grid's own `groupKeys`, reduced to its
   * last entry — the immediate parent). Omitted at the root level, where
   * `tree=true` alone selects "roots only".
   */
  treeParentId?: number | null
}

/** Pagination metadata from the `paginatedResponse()` envelope. */
export interface RowsPaginationMeta {
  total: number
  offset: number
  limit: number
  total_pages: number
}

/**
 * Aggregate figures computed server-side over the WHOLE filtered result set
 * (not just the current page), keyed by an arbitrary aggregate name (e.g.
 * `estimated_minutes_total`, spec 0156 D-3). A domain with no `aggregates()`
 * override sends no `meta` at all — see `TableRowsResponse.meta`.
 */
export type TableRowsAggregates = Record<string, number>

/** Response of POST /tables/{domain}/rows (`paginatedResponse()` envelope). */
export interface TableRowsResponse {
  items: TableRow[]
  export_link: string | null
  pagination: RowsPaginationMeta
  /**
   * Present only for a domain overriding `TableDefinition::aggregates()`
   * (spec 0156 D-3); absent for every other domain, unchanged.
   */
  meta?: { aggregates: TableRowsAggregates }
}

/**
 * POST /tables/{domain}/values request payload: distinct values for one
 * column's Set Filter, server-side and Excel-like (0004). `filterModel`
 * carries the filters currently active on the OTHER columns; the backend
 * ignores an entry for `columnId` itself.
 */
export interface TableColumnValuesPayload {
  columnId: string
  search?: string
  limit?: number
  filterModel?: Record<string, unknown>
  /**
   * The selected Product Category tab (spec 0064, request-management only):
   * required by the backend when `columnId` is an `attr.<code>` set filter,
   * so it can resolve that column against the category's effective
   * attributes. Omitted for every native/`custom.*` column.
   */
  productCategoryId?: number | null
  /** Row-set scope to one parent record (spec 0067 D-1), same rule as above. */
  opportunityId?: number | null
  /** Row-set scope to one Contract's Offerta (spec 0095 D-8), same rule as above. */
  quoteId?: number | null
}

/** Response of POST /tables/{domain}/values (envelope `data`). */
export interface TableColumnValuesResponse {
  /**
   * The distinct values offered by the Set Filter. A `null` entry is AG Grid's
   * own blank option (rendered "(Vuoti)"): the backend emits it when the column
   * holds empty cells, and sends it back unchanged inside the filter model.
   */
  values: (string | null)[]
  hasMore: boolean
}

/** Why a single row was skipped by a bulk-delete request. */
export type BulkDeleteFailureReason = 'forbidden' | 'guarded' | 'not_found'

/** One row that could not be deleted, with the reason it was skipped. */
export interface BulkDeleteFailure {
  id: number
  reason: BulkDeleteFailureReason
}

/** Response of POST /tables/{domain}/bulk-delete (envelope `data`). */
export interface BulkDeleteResult {
  deleted: number
  failed: BulkDeleteFailure[]
}

/**
 * Visibility of a saved filter view (spec 0007). A `private` view is visible
 * only to its owner; a `shared` view is visible/appliable by every user who
 * can view the domain's table.
 */
export type FilterViewVisibility = 'private' | 'shared'

/**
 * A saved filter view (`TableFilterViewResource`), returned by
 * GET/POST/PUT /tables/{domain}/filter-views. `owned` mirrors
 * `view.user_id === auth id`. `owner_name` is present only when the view is
 * shared and NOT owned by the actor (so the UI can show "shared by X"); `null`
 * otherwise. Never carries owner email/PII, display name only.
 */
export interface TableFilterView {
  id: number
  name: string
  filters: Record<string, unknown>
  /**
   * Advanced filters (spec 0032) captured by this view, keyed like
   * `TableConfig.appliedAdvancedFilters`. Always present (`{}` when the view
   * predates this field or was saved with none) — mirrors the backend
   * resource, which never omits the key.
   */
  advanced_filters: AdvancedFilterValues
  visibility: FilterViewVisibility
  owned: boolean
  owner_name: string | null
}

/** Body sent to create/update a saved filter view. */
export interface FilterViewInput {
  name: string
  filters: Record<string, unknown>
  /** Advanced filters (spec 0032) applied at save time; omitted ⇒ none. */
  advancedFilters?: AdvancedFilterValues
  visibility: FilterViewVisibility
}
