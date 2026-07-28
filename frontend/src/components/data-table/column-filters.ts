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
import { fetchTableColumnValues } from '@/features/table/api'
import type { TableColumn } from '@/features/table/types'
import { enumLabelOf } from '@/features/config/enum-label'

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
 * `productCategoryId` (spec 0064, request-management's category tabs) rides
 * along so an `attr.<code>` column's set filter resolves against the right
 * category; omitted for every native/`custom.*` column.
 */
export function createColumnValuesGetter(
  domain: string,
  columnId: string,
  onTruncated: () => void,
  productCategoryId?: number,
): SetFilterValuesFunc {
  return (params: SetFilterValuesFuncParams) => {
    const filterModel: Record<string, unknown> = { ...params.api.getFilterModel() }
    delete filterModel[columnId]

    fetchTableColumnValues(domain, {
      columnId,
      filterModel,
      ...(productCategoryId != null ? { productCategoryId } : {}),
    })
      .then((response) => {
        if (response.hasMore) {
          onTruncated()
        }
        params.success(response.values)
      })
      .catch(() => params.success([]))
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
 */
export function buildSetFilterParams(
  domain: string,
  column: TableColumn,
  onTruncated: () => void,
  translate: (key: string) => string,
  productCategoryId?: number,
): ISetFilterParams {
  const params: ISetFilterParams = {
    values: createColumnValuesGetter(domain, column.id, onTruncated, productCategoryId),
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
  if (column.type === 'badge') {
    params.valueFormatter = (formatterParams: ValueFormatterParams): string =>
      formatBadgeFilterValue(formatterParams.value, column)
  }
  return params
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
): { filter: ColDef['filter']; filterParams: ColDef['filterParams'] } {
  const filter = resolveFilter(column)
  if (filter === 'agSetColumnFilter') {
    return {
      filter,
      filterParams: buildSetFilterParams(domain, column, onTruncated, translate, productCategoryId),
    }
  }
  if (filter === 'agMultiColumnFilter') {
    const typedFilter = resolveTypedFilter(column)
    const filterParams: IMultiFilterParams = {
      filters: [
        {
          filter: 'agSetColumnFilter',
          filterParams: buildSetFilterParams(domain, column, onTruncated, translate, productCategoryId),
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
