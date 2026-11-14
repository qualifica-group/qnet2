/**
 * Column -> AG Grid filter resolution for the generic DataTable (0004: Excel-like
 * filters). Pure logic, no JSX, so it can export plain functions alongside the
 * component-only `data-table.tsx` without tripping react-refresh.
 */
import type {
  ColDef,
  IMultiFilterParams,
  ISetFilterParams,
  SetFilterValuesFunc,
  SetFilterValuesFuncParams,
  ValueFormatterParams,
} from 'ag-grid-community'
import { toDateServerSearch } from '@/components/data-table/date-filter-search'
import { withPresetSetFilterValues } from '@/components/data-table/preset-set-filter-values'
import {
  checkServerSearchMatches,
  markSetFilterTruncated,
  setFilterServerSearch,
} from '@/components/data-table/set-filter-server-search'
import { fetchTableColumnValues } from '@/features/table/api'
import type { TableColumn } from '@/features/table/types'
import { enumLabelOf } from '@/features/config/enum-label'
import { formatDate, formatDateTimeOptionalTime } from '@/lib/formatting/date-display'

/**
 * Maps a backend column to the AG Grid filter component, or false.
 *
 * Prefers the explicit `filterType` from the config contract (0002) when
 * present, falling back to the column `type` for backward compatibility.
 * set/enum/tags/badge/boolean columns get the standalone `agSetColumnFilter`
 * (0004). text/number/date(-time) columns get the combined `agMultiColumnFilter`
 * (Set Filter + a typed condition, see `resolveTypedFilter`) UNLESS the column
 * is computed/derived without a queryable value list (`hasFilterValues ===
 * false`, 0005 — e.g. a concatenated address), in which case they fall back to
 * the plain typed condition filter alone, with no Set tab.
 */
export function resolveFilter(column: TableColumn): ColDef['filter'] {
  if (!column.filterable) {
    return false
  }
  const kind = column.filterType ?? column.type
  switch (kind) {
    case 'set':
    case 'enum':
    case 'tags':
    case 'badge':
    case 'boolean':
      return 'agSetColumnFilter'
    default:
      return column.hasFilterValues === false
        ? resolveTypedFilter(column)
        : 'agMultiColumnFilter'
  }
}

/**
 * The typed condition filter for text/number/date columns: stacked alongside
 * the Set Filter inside an `agMultiColumnFilter`, or used standalone for
 * computed columns that have no Set value list (`hasFilterValues === false`).
 */
export function resolveTypedFilter(
  column: TableColumn,
): 'agNumberColumnFilter' | 'agDateColumnFilter' | 'agTextColumnFilter' {
  const kind = column.filterType ?? column.type
  switch (kind) {
    case 'number':
      return 'agNumberColumnFilter'
    case 'date':
    case 'datetime':
      return 'agDateColumnFilter'
    default:
      return 'agTextColumnFilter'
  }
}

/**
 * Builds the Set Filter's async `values` callback for one column: fetches the
 * distinct values from POST /tables/{domain}/values, scoped to the filters
 * currently active on the OTHER columns — the column never self-restricts its
 * own list (Excel behavior, 0004). Reads the live filter model off the grid
 * API rather than a snapshot, so it always reflects the state at the moment
 * the filter is opened (paired with `refreshValuesOnOpen`). A fetch failure
 * resolves to an empty list so the filter UI never crashes.
 *
 * Once the list came back truncated, the checklist's search text rides along
 * as `search` (task 427169, see `set-filter-server-search.ts`), rewritten by
 * `toServerSearch` when the column needs it (dates, task 427789).
 *
 * `productCategoryId` (spec 0064, request-management's category tabs) rides
 * along so an `attr.<code>` column's set filter resolves against the right
 * category; omitted for every native/`custom.*` column.
 *
 * `opportunityId` (spec 0067 D-1, the Opportunity detail's Quotes panel) rides
 * along the same way, so a set filter's value list is restricted to that
 * Opportunity's Offerte; omitted for every domain but `quotes`.
 *
 * `quoteId` (spec 0095 D-8, the Contract detail's Commesse tab) rides along
 * the same way, restricting the value list to that Contract's Offerta;
 * omitted for every domain but `work-orders`.
 */
export function createColumnValuesGetter(
  domain: string,
  columnId: string,
  onTruncated: () => void,
  productCategoryId?: number,
  opportunityId?: number,
  quoteId?: number,
  registryId?: number,
  referentId?: number,
  productCategoryBranchId?: number,
  toServerSearch?: (search: string) => string,
): SetFilterValuesFunc {
  return (params: SetFilterValuesFuncParams) => {
    const filterModel: Record<string, unknown> = { ...params.api.getFilterModel() }
    delete filterModel[columnId]

    const load = async (): Promise<void> => {
      const search = await setFilterServerSearch(params.api, columnId)
      const response = await fetchTableColumnValues(domain, {
        columnId,
        filterModel,
        ...(search !== null ? { search: toServerSearch ? toServerSearch(search) : search } : {}),
        ...(productCategoryId != null ? { productCategoryId } : {}),
        ...(productCategoryBranchId != null ? { productCategoryBranchId } : {}),
        ...(opportunityId != null ? { opportunityId } : {}),
        ...(quoteId != null ? { quoteId } : {}),
        ...(registryId != null ? { registryId } : {}),
        ...(referentId != null ? { referentId } : {}),
      })
      if (response.hasMore) {
        markSetFilterTruncated(params.api, columnId)
        onTruncated()
      }
      params.success(withPresetSetFilterValues(params.api, columnId, response.values))
      if (search !== null) {
        // After AG Grid has taken the new list (it settles on microtasks).
        setTimeout(() => void checkServerSearchMatches(params.api, columnId), 0)
      }
    }

    load().catch(() => params.success([]))
  }
}

/**
 * Set Filter params shared by both the standalone Set Filter and the one
 * inlined inside `agMultiColumnFilter`. `excelMode: 'windows'` gives it the
 * classic Excel checklist chrome (search + Select All + Apply/Reset), so
 * every filterable column reads the same regardless of widget (0005).
 * `refreshValuesOnOpen` re-fetches every time the popup opens (the OTHER
 * columns' filters may have changed since); `suppressClearModelOnRefreshValues`
 * keeps the current selection even if a refreshed value list temporarily
 * doesn't include it yet — recommended by AG Grid for SSRM setups that derive
 * one column's values from the others.
 *
 * A date column lists its values in the user's date pattern, like its cells,
 * and its search is rewritten for the stored values (task 427789).
 */
export function buildSetFilterParams(
  domain: string,
  column: TableColumn,
  onTruncated: () => void,
  translate: (key: string) => string,
  productCategoryId?: number,
  opportunityId?: number,
  quoteId?: number,
  registryId?: number,
  referentId?: number,
  productCategoryBranchId?: number,
): ISetFilterParams {
  const isDate = isDateColumn(column)
  const params: ISetFilterParams = {
    values: createColumnValuesGetter(
      domain,
      column.id,
      onTruncated,
      productCategoryId,
      opportunityId,
      quoteId,
      registryId,
      referentId,
      productCategoryBranchId,
      isDate ? toDateServerSearch : undefined,
    ),
    refreshValuesOnOpen: true,
    suppressClearModelOnRefreshValues: true,
    excelMode: 'windows',
  }
  // Boolean columns carry raw 1/0 (real columns) or true/false (definition
  // overrides); localize the checklist labels to Sì/No like the cell renderer,
  // while the underlying value round-trips unchanged to the backend filter.
  if (column.type === 'boolean') {
    params.valueFormatter = (formatterParams: ValueFormatterParams): string =>
      formatBooleanFilterValue(formatterParams.value, translate)
  }
  // `tags` too (spec 0142 `usages`): each value of a multi-valued enum cell
  // is one checklist entry, localized the same way as its cell.
  if (column.type === 'badge' || column.type === 'tags') {
    params.valueFormatter = (formatterParams: ValueFormatterParams): string =>
      formatBadgeFilterValue(formatterParams.value, column)
  }
  if (isDate) {
    const format = column.type === 'datetime' ? formatDateTimeOptionalTime : formatDate
    params.valueFormatter = (formatterParams: ValueFormatterParams): string => format(formatterParams.value)
  }
  return params
}

function isDateColumn(column: TableColumn): boolean {
  const kind = column.filterType ?? column.type

  return kind === 'date' || kind === 'datetime'
}

const BOOLEAN_YES_KEY = 'common.yes'
const BOOLEAN_NO_KEY = 'common.no'

/**
 * Maps a boolean column's raw distinct value to its localized Set Filter label.
 * Accepts both shapes the backend can emit — `"1"`/`"0"` strings (real columns)
 * and `true`/`false` (definition overrides). Display only. Exported so the
 * grid's generic `source:'custom'` boolean cell fallback (data-table.tsx)
 * reuses the same coercion instead of re-implementing it.
 */
export function formatBooleanFilterValue(
  value: unknown,
  translate: (key: string) => string,
): string {
  if (value === null || value === undefined || value === '') {
    return ''
  }
  const isTruthy = value === true || value === 1 || value === '1' || value === 'true'
  return translate(isTruthy ? BOOLEAN_YES_KEY : BOOLEAN_NO_KEY)
}

/**
 * Maps a single enum/badge raw value (a backend `code`) to its label, via the
 * column's `enumKey` (frontend i18n) or its `badges` catalog, raw value when
 * neither matches. Exported so the grid's generic `tags` cell/value formatter
 * (`column-defaults.tsx`) reuses the same lookup for a multiselect enum
 * attribute (spec 0064) instead of showing the raw array of codes.
 */
export function formatBadgeFilterValue(value: unknown, column: TableColumn): string {
  if (value === null || value === undefined || value === '') {
    return ''
  }

  const raw = String(value)
  if (column.enumKey) {
    return enumLabelOf(column.enumKey, raw)
  }

  const badge = column.badges?.find((candidate) => candidate.value === raw)

  return badge?.label ?? raw
}

/** i18n key for the sub-menu title of the typed condition filter (0005). */
const TYPED_FILTER_TITLE_KEY: Record<
  ReturnType<typeof resolveTypedFilter>,
  string
> = {
  agTextColumnFilter: 'table.textFilters',
  agNumberColumnFilter: 'table.numberFilters',
  agDateColumnFilter: 'table.dateFilters',
}

/**
 * Full `filter`/`filterParams` pair for a column's AG Grid `ColDef`.
 *
 * Excel-classic layout (0005): a multi-filter column shows the Set Filter
 * checklist INLINE as the primary view, with the typed condition filter
 * tucked into a titled sub-menu — one consistent panel across every
 * filterable column, not a tab switch. Standalone Set Filter columns
 * (set/enum/tags/badge/boolean) get the same checklist chrome directly. A
 * plain typed condition filter (computed columns, `hasFilterValues === false`)
 * and non-filterable columns fall through with no params — no Set Filter, so
 * no values-callback is ever built for them.
 */
export function buildColumnFilter(
  domain: string,
  column: TableColumn,
  onTruncated: () => void,
  translate: (key: string) => string,
  productCategoryId?: number,
  opportunityId?: number,
  quoteId?: number,
  registryId?: number,
  referentId?: number,
  productCategoryBranchId?: number,
): { filter: ColDef['filter']; filterParams: ColDef['filterParams'] } {
  const filter = resolveFilter(column)
  if (filter === 'agSetColumnFilter') {
    return {
      filter,
      filterParams: buildSetFilterParams(domain, column, onTruncated, translate, productCategoryId, opportunityId, quoteId, registryId, referentId, productCategoryBranchId),
    }
  }
  if (filter === 'agMultiColumnFilter') {
    const typedFilter = resolveTypedFilter(column)
    const filterParams: IMultiFilterParams = {
      filters: [
        {
          filter: 'agSetColumnFilter',
          filterParams: buildSetFilterParams(domain, column, onTruncated, translate, productCategoryId, opportunityId, quoteId, registryId, referentId, productCategoryBranchId),
        },
        {
          filter: typedFilter,
          display: 'subMenu',
          title: translate(TYPED_FILTER_TITLE_KEY[typedFilter]),
        },
      ],
    }
    return { filter, filterParams }
  }
  return { filter, filterParams: undefined }
}
