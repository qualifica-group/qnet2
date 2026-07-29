import type { ReactNode } from 'react'
import type { ColDef, ICellRendererParams } from 'ag-grid-community'
import type { TFunction } from 'i18next'
import { toast } from 'sonner'
import { buildColumnFilter } from '@/components/data-table/column-filters'
import {
  defaultValueFormatter,
  resolveCellRenderer,
  resolveEditableColumnProps,
  type CellRenderer,
} from '@/components/data-table/column-defaults'
import { ACTIONS_COLUMN_ID } from '@/components/data-table/data-table-overlays'
import type { TableColumn } from '@/features/table/types'

/** Default minimum width for data columns without an explicit backend width. */
export const DEFAULT_MIN_WIDTH = 120

/**
 * Fixed width of the row-actions column. The default holds up to three compact
 * icon buttons; when the domain exposes more actions a fourth (overflow) button
 * appears, so the column gets a bit wider to fit it. Kept narrow either way
 * because it only holds those controls.
 */
const ACTIONS_COLUMN_WIDTH = 100
const ACTIONS_COLUMN_WIDTH_WITH_OVERFLOW = 120

/**
 * Synthetic leading column that carries the master/detail expand chevron. AG
 * Grid renders no expand toggle on its own when `masterDetail` is enabled: a
 * column must host the built-in `agGroupCellRenderer`. Added only when
 * `masterDetail` is on, so the other domains keep exactly their current layout.
 */
const MASTER_DETAIL_EXPAND_COLUMN_ID = '__expand'
const MASTER_DETAIL_EXPAND_COLUMN_WIDTH = 44

export interface BuildColDefsParams {
  domain: string
  /** Selected Product Category scope (spec 0064), forwarded to the Set Filter values callback. */
  productCategoryId?: number
  /** Opportunity row-set scope (spec 0067 D-1), forwarded to the Set Filter values callback. */
  opportunityId?: number
  columns: TableColumn[]
  cellRenderers?: Record<string, CellRenderer>
  renderRowActions?: (params: ICellRendererParams) => ReactNode
  actionsHeaderLabel?: string
  actionsColumnHasOverflow?: boolean
  masterDetail?: boolean
  t: TFunction
}

/**
 * Translates the backend column schema into AG Grid `ColDef[]`, prepending the
 * synthetic leading columns (row actions, master/detail expand) the wrapper
 * injects. Pure and domain-agnostic — the domain only leaks in through the
 * async Set Filter values callback keyed by `domain`.
 */
export function buildColDefs({
  domain,
  productCategoryId,
  opportunityId,
  columns,
  cellRenderers,
  renderRowActions,
  actionsHeaderLabel,
  actionsColumnHasOverflow,
  masterDetail,
  t,
}: BuildColDefsParams): ColDef[] {
  const mapped: ColDef[] = columns.map((column) => {
    // Generic, domain-agnostic renderer selection (badge/enum fallback) and
    // value-formatter selection (custom boolean/number) — see
    // column-defaults.tsx. No per-id renderer needed even for dynamic
    // `custom.<key>` columns.
    const renderer = resolveCellRenderer(column, cellRenderers)
    const valueFormatter = renderer ? undefined : defaultValueFormatter(column, t)
    // A column with a persisted width uses it as a fixed width (flex:0 opts it
    // out of the flex layout); columns without one keep flexing to fill space
    // via defaultColDef.initialFlex. Columns arrive already ordered by `order`.
    const hasWidth = column.width != null
    // Every Set Filter (standalone or nested in the Multi Filter) gets its
    // values from the server, never a backend one-off list or the paged
    // client rows (0004) — see `buildColumnFilter`.
    const { filter, filterParams } = buildColumnFilter(
      domain,
      column,
      () => toast.info(t('table.filterValuesTruncated')),
      t,
      productCategoryId,
      opportunityId,
    )
    return {
      colId: column.id,
      field: column.id,
      headerName: t(column.label),
      // `initial*`, NOT `hide`/`width`/`flex`: the backend layout SEEDS the grid
      // at column creation and the grid owns it from then on. AG Grid re-applies
      // the column definitions on any `columnDefs`/`defaultColDef` identity
      // change, and `_updateColumnState` reads only the non-`initial` keys — so
      // with `flex`/`width` the defaultColDef's `flex: 1` was pushed back onto
      // every column on the next React render, discarding a width the user had
      // just dragged. The `initial` keys are read only by `Column.initState()`.
      initialHide: !column.visible,
      initialWidth: hasWidth ? column.width! : undefined,
      // Let an intentionally-narrow backend width take effect: without this the
      // global DEFAULT_MIN_WIDTH would clamp it (e.g. the small avatar column).
      minWidth: hasWidth ? Math.min(DEFAULT_MIN_WIDTH, column.width!) : undefined,
      initialFlex: hasWidth ? 0 : undefined,
      sortable: column.sortable,
      filter,
      filterParams,
      cellRenderer: renderer
        ? (params: ICellRendererParams) => renderer(params)
        : undefined,
      valueFormatter: valueFormatter
        ? (params) => valueFormatter(params.value)
        : undefined,
      ...resolveEditableColumnProps(column),
    }
  })

  if (renderRowActions) {
    const actionsWidth = actionsColumnHasOverflow
      ? ACTIONS_COLUMN_WIDTH_WITH_OVERFLOW
      : ACTIONS_COLUMN_WIDTH
    // Leading column: pinned left and placed before every data column so the
    // row actions stay reachable without scrolling to the end of a wide table.
    mapped.unshift({
      colId: ACTIONS_COLUMN_ID,
      headerName: actionsHeaderLabel ? t(actionsHeaderLabel) : '',
      sortable: false,
      filter: false,
      resizable: false,
      pinned: 'left',
      width: actionsWidth,
      minWidth: actionsWidth,
      flex: 0,
      // Synthetic column, not part of the domain schema: hiding it from the
      // tool panel keeps the list to real, persistable columns (its id is not
      // in the server's allow-list, so it is dropped on save anyway).
      suppressColumnsToolPanel: true,
      cellRenderer: (params: ICellRendererParams) => renderRowActions(params),
    })
  }

  // Master/Detail expand toggle (spec 0059 D-4): the leftmost column so the
  // chevron precedes even the pinned actions column. `agGroupCellRenderer` is
  // the built-in that draws the expand/collapse control; without a column
  // hosting it, `masterDetail` enables the detail panel but leaves no way to
  // open a row.
  if (masterDetail) {
    mapped.unshift({
      colId: MASTER_DETAIL_EXPAND_COLUMN_ID,
      headerName: '',
      cellRenderer: 'agGroupCellRenderer',
      sortable: false,
      filter: false,
      resizable: false,
      pinned: 'left',
      width: MASTER_DETAIL_EXPAND_COLUMN_WIDTH,
      minWidth: MASTER_DETAIL_EXPAND_COLUMN_WIDTH,
      maxWidth: MASTER_DETAIL_EXPAND_COLUMN_WIDTH,
      flex: 0,
      suppressColumnsToolPanel: true,
    })
  }

  return mapped
}
