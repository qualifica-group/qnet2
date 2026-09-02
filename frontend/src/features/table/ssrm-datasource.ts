import type {
  IServerSideDatasource,
  IServerSideGetRowsParams,
} from 'ag-grid-community'
import { fetchTableRows } from '@/features/table/api'
import type { SsrmSortModelItem, TableRow } from '@/features/table/types'
import type { AdvancedFilterValues } from '@/features/table/advanced-filters/types'

/** Page size fallback when the grid does not provide an explicit block range. */
const DEFAULT_BLOCK_SIZE = 25

/**
 * Builds an AG Grid Server-Side Row Model datasource for a given `domain`.
 *
 * The datasource is a thin adapter: it forwards the SSRM request (startRow,
 * endRow, sortModel, filterModel) to `POST /tables/{domain}/rows` via the API
 * layer, maps the `paginatedResponse()` envelope (`items` + `pagination.total`)
 * to `params.success({ rowData, rowCount })`, and signals failures with
 * `params.fail()` so the grid can recover its state.
 *
 * The global quick-search term (spec 0009) and the applied advanced filters
 * (spec 0032) are NOT part of the AG Grid SSRM request, so the caller supplies
 * them through the `getSearch`/`getAdvancedFilters` getters, read at request
 * time. The datasource instance stays stable across renders; the caller calls
 * `refreshServerSide({ purge: true })` when either changes.
 *
 * `productCategoryId` (spec 0064, request-management's category tabs) is a
 * plain value, not a getter like the two above: selecting a category always
 * remounts the whole `<TableView>` (its adapter keys it by the selection, D-4)
 * so a fresh datasource is built per tab already — there is nothing to read
 * lazily. Sent as `productCategoryId` only when present; omitted entirely on
 * the "Tutte" tab.
 *
 * `opportunityId` (spec 0067 D-1, the Opportunity detail's Quotes panel) is
 * the same kind of plain value, sent as `opportunityId` only when present —
 * a no-op for every domain but `quotes`.
 *
 * `quoteId` (spec 0095 D-8, the Contract detail's Commesse tab) is the same
 * kind of plain value, sent as `quoteId` only when present — a no-op for
 * every domain but `work-orders`.
 *
 * Domain-agnostic: the only domain-specific input is the `domain` key. The same
 * datasource powers every table.
 */
export function createSsrmDatasource(
  domain: string,
  getSearch?: () => string,
  getAdvancedFilters?: () => AdvancedFilterValues,
  productCategoryId?: number,
  opportunityId?: number,
  quoteId?: number,
): IServerSideDatasource<TableRow> {
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

      try {
        const response = await fetchTableRows(domain, {
          startRow,
          endRow,
          sortModel,
          filterModel,
          ...(search !== '' ? { search } : {}),
          ...(Object.keys(advancedFilters).length > 0 ? { advancedFilters } : {}),
          ...(productCategoryId != null ? { productCategoryId } : {}),
          ...(opportunityId != null ? { opportunityId } : {}),
          ...(quoteId != null ? { quoteId } : {}),
        })

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
