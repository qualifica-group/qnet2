import type {
  IServerSideDatasource,
  IServerSideGetRowsParams,
} from 'ag-grid-community'
import { fetchTableRows } from '@/features/table/api'
import type { FilterRules, SsrmSortModelItem, TableRow, TableRowsAggregates } from '@/features/table/types'
import type { AdvancedFilterValues } from '@/features/table/advanced-filters/types'

/** Page size fallback when the grid does not provide an explicit block range. */
const DEFAULT_BLOCK_SIZE = 25

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
): IServerSideDatasource<TableRow> {
  const {
    getSearch,
    getAdvancedFilters,
    getCustomFilterRules,
    productCategoryId,
    opportunityId,
    quoteId,
    onAggregates,
    treeData,
  } = options

  return {
    async getRows(params: IServerSideGetRowsParams<TableRow>): Promise<void> {
      const { request } = params
      const startRow = request.startRow ?? 0
      const endRow = request.endRow ?? startRow + DEFAULT_BLOCK_SIZE

      const sortModel: SsrmSortModelItem[] = request.sortModel.map((item) => ({
        colId: item.colId,
        sort: item.sort,
      }))

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
          ...(treeData ? { tree: true } : {}),
          ...(treeParentId != null ? { treeParentId } : {}),
        })

        onAggregates?.(response.meta?.aggregates)
        params.success({
          rowData: response.items,
          rowCount: response.pagination.total,
        })
      } catch {
        params.fail()
      }
    },
  }
}
