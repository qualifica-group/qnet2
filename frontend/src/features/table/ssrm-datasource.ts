import type {
  IServerSideDatasource,
  IServerSideGetRowsParams,
} from 'ag-grid-community'
import { fetchTableRows } from '@/features/table/api'
import type { FilterRules, SsrmSortModelItem, TableRow, TableRowsAggregates } from '@/features/table/types'
import type { AdvancedFilterValues } from '@/features/table/advanced-filters/types'
import {
  isGroupItem,
  resolveGroupSortModel,
  toGroupRow,
  type RowGroupingOptions,
} from '@/features/table/row-grouping'

/** Page size fallback when the grid does not provide an explicit block range. */
const DEFAULT_BLOCK_SIZE = 25

/**
 * Serializes a value with object keys sorted at every level, so two
 * structurally equal filter payloads always yield the same string
 * regardless of key insertion order.
 */
function stableStringify(value: unknown): string {
  if (Array.isArray(value)) return `[${value.map(stableStringify).join(',')}]`
  if (value !== null && typeof value === 'object') {
    const record = value as Record<string, unknown>
    const entries = Object.keys(record)
      .filter((key) => record[key] !== undefined)
      .sort()
      .map((key) => `${JSON.stringify(key)}:${stableStringify(record[key])}`)
    return `{${entries.join(',')}}`
  }
  return JSON.stringify(value) ?? 'null'
}

/**
 * Total learned from block 0 for one request signature (spec 0178 D-1). No
 * aggregates: the footer state already holds the last ones (AC-008).
 */
interface KnownTotalMemo {
  signature: string
  total: number
}

/** Datasource plus the reset hook `refreshGrid` calls before a purge (spec 0178 D-2). */
export type SsrmDatasource = IServerSideDatasource<TableRow> & { resetKnownTotal(): void }

/**
 * Options accepted by `createSsrmDatasource`. Every entry beyond `domain` is a
 * no-op for a domain/call site that does not use it — kept as an options
 * object (rather than positional parameters) so a new one never forces every
 * existing caller to update its call, and so this module stays under the
 * engineering.md §6 size budget as the framework grows.
 */
export interface SsrmDatasourceOptions {
  /** Global quick-search term (spec 0009); read lazily so typing never rebuilds the datasource. */
  getSearch?: () => string
  /** Applied advanced filters (spec 0032); read lazily, same reasoning as `getSearch`. */
  getAdvancedFilters?: () => AdvancedFilterValues
  /**
   * The active custom filter's rules (spec 0158), read lazily. When it
   * returns non-null, `customFilterRules` is sent INSTEAD of relying on
   * `filterModel`/`getAdvancedFilters` (the backend ignores both once this key
   * is present) — the caller is expected to already have cleared them as part
   * of activating the custom filter, so this is additive, not a substitution
   * performed here.
   */
  getCustomFilterRules?: () => FilterRules | null
  /** Product Category tab scope (spec 0064); a no-op for every domain but `request-management`. */
  productCategoryId?: number
  /** Row-set scope to one Opportunity's Quotes panel (spec 0067 D-1); a no-op for every domain but `quotes`. */
  opportunityId?: number
  /** Row-set scope to one Contract's Offerta (spec 0095 D-8); a no-op for every domain but `work-orders`. */
  quoteId?: number
  /** Reports each response's `meta.aggregates` (spec 0156 D-3); a no-op for a domain with no `aggregates()` override. */
  onAggregates?: (aggregates: TableRowsAggregates | undefined) => void
  /** Server-side tree data (spec 0157 D-1); a no-op for every domain but `tasks`. */
  treeData?: boolean
  /** Server-side row grouping (spec 0197); a no-op (flat rows) when omitted, i.e. for every domain without it. */
  rowGrouping?: RowGroupingOptions
}

/**
 * Builds an AG Grid Server-Side Row Model datasource for a given `domain`.
 *
 * The datasource is a thin adapter: it forwards the SSRM request (startRow,
 * endRow, sortModel, filterModel) to `POST /tables/{domain}/rows` via the API
 * layer, maps the `paginatedResponse()` envelope (`items` + `pagination.total`)
 * to `params.success({ rowData, rowCount })`, and signals failures with
 * `params.fail()` so the grid can recover its state.
 *
 * Domain-agnostic: the only required input is the `domain` key. The same
 * datasource powers every table; every other behavior is opt-in through
 * `options` (see `SsrmDatasourceOptions`).
 */
export function createSsrmDatasource(
  domain: string,
  options: SsrmDatasourceOptions = {},
): SsrmDatasource {
  const {
    getSearch,
    getAdvancedFilters,
    getCustomFilterRules,
    productCategoryId,
    opportunityId,
    quoteId,
    onAggregates,
    treeData,
    rowGrouping,
  } = options

  // Spec 0178 D-1: the block-0 total, valid only for the signature it was
  // counted under. Lives in the closure: one datasource per domain/scope.
  let memo: KnownTotalMemo | null = null

  return {
    // Spec 0178 D-2: after a write, even a page > 1 must recount.
    resetKnownTotal(): void {
      memo = null
    },

    async getRows(params: IServerSideGetRowsParams<TableRow>): Promise<void> {
      const { request } = params
      const startRow = request.startRow ?? 0
      const endRow = request.endRow ?? startRow + DEFAULT_BLOCK_SIZE

      // Spec 0197: the grouped columns, capped at the configured depth (the grid
      // enforces it too, this is the last line of defence against a 422).
      const rowGroupCols = rowGrouping
        ? request.rowGroupCols.map((column) => column.id).slice(0, rowGrouping.maxDepth)
        : []
      const grouped = rowGroupCols.length > 0
      const groupKeys = grouped ? request.groupKeys.map(String) : []
      // Only the root level's aggregates are the footer's grand totals.
      const reportsAggregates = !grouped || groupKeys.length === 0

      const plainSortModel: SsrmSortModelItem[] = request.sortModel.map((item) => ({
        colId: item.colId,
        sort: item.sort,
      }))
      const sortModel =
        grouped && rowGrouping
          ? resolveGroupSortModel(plainSortModel, rowGroupCols, groupKeys.length, rowGrouping.aggColumnIds)
          : plainSortModel

      // filterModel can be a plain map, an advanced model, or null — normalize to
      // the simple object the backend contract validates against.
      const filterModel: Record<string, unknown> =
        request.filterModel && !Array.isArray(request.filterModel)
          ? (request.filterModel as Record<string, unknown>)
          : {}

      // Only send `search` when there is a non-empty term (keeps the request
      // clean and lets the backend skip the OR-LIKE entirely otherwise).
      const search = getSearch?.().trim() ?? ''

      // Same treatment for the applied advanced filters (spec 0032): omitted
      // entirely when there is none applied.
      const advancedFilters = getAdvancedFilters?.() ?? {}

      // The active custom filter (spec 0158), when one is applied.
      const customFilterRules = getCustomFilterRules?.() ?? null

      // `groupKeys` is empty at the root; its last entry is the immediate
      // parent once the grid asks for a level below it.
      const treeParentId =
        treeData && request.groupKeys.length > 0
          ? Number(request.groupKeys[request.groupKeys.length - 1])
          : undefined

      // Spec 0178 D-1: everything that changes the row COUNT, and nothing else
      // (sortModel is deliberately excluded: ordering never changes the count).
      const signature = stableStringify({
        filterModel,
        search,
        advancedFilters,
        customFilterRules,
        productCategoryId,
        opportunityId,
        quoteId,
        tree: treeData ?? false,
        treeParentId,
        rowGroupCols,
        groupKeys,
      })
      // Block 0 always recounts server-side; later blocks reuse the memo only
      // when it was counted under the same signature.
      const knownTotal = startRow > 0 && memo?.signature === signature ? memo.total : undefined

      try {
        const response = await fetchTableRows(domain, {
          startRow,
          endRow,
          sortModel,
          filterModel,
          ...(search !== '' ? { search } : {}),
          ...(Object.keys(advancedFilters).length > 0 ? { advancedFilters } : {}),
          ...(customFilterRules ? { customFilterRules } : {}),
          ...(productCategoryId != null ? { productCategoryId } : {}),
          ...(opportunityId != null ? { opportunityId } : {}),
          ...(quoteId != null ? { quoteId } : {}),
          ...(grouped ? { rowGroupCols, groupKeys } : {}),
          ...(treeData ? { tree: true } : {}),
          ...(treeParentId != null ? { treeParentId } : {}),
          ...(knownTotal !== undefined ? { knownTotal } : {}),
        })

        // Spec 0178 AC-008: a response served with knownTotal carries no
        // `meta`; keep the footer's last aggregates instead of clearing them.
        if (knownTotal === undefined) {
          memo = { signature, total: response.pagination.total }
          if (reportsAggregates) {
            onAggregates?.(response.meta?.aggregates)
          }
        }
        params.success({
          rowData: grouped
            ? response.items.map((item) => (isGroupItem(item) ? toGroupRow(item) : item))
            : response.items,
          rowCount: response.pagination.total,
        })
      } catch {
        params.fail()
      }
    },
  }
}
