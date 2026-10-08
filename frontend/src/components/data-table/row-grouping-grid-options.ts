import type {
  ColDef,
  GetRowIdParams,
  GridApi,
  GridOptions,
  ICellRendererParams,
} from 'ag-grid-community'
import type { TFunction } from 'i18next'
import {
  GROUP_ROW_CHILD_COUNT,
  GROUP_ROW_FLAG,
  GROUP_ROW_KEY,
  GROUP_ROW_LABEL,
  NULL_GROUP_KEY,
} from '@/features/table/row-grouping'
import type { TableColumn, TableRow, TableRowGroupingConfig } from '@/features/table/types'

/** Wider than a data column: the group cell hosts the expand chevron, the label and the count. */
const GROUP_COLUMN_MIN_WIDTH = 220

/**
 * Server-side row grouping grid options (spec 0197 D-4), split out of
 * `data-table.tsx` to keep it under the size budget. Returns `{}` when the
 * domain's config has no enabled `row_grouping`, so spreading the result into
 * `gridOptions` is a no-op for every other domain.
 */
export function buildRowGroupingGridOptions(
  rowGrouping: TableRowGroupingConfig | undefined,
  t: TFunction,
): Partial<GridOptions<TableRow>> {
  if (!rowGrouping?.enabled) {
    return {}
  }

  return {
    // The RowGroupingBar picker replaces the drag-a-header-here panel, which
    // users read as "drag a row" and found unusable.
    rowGroupPanelShow: 'never',
    groupDefaultExpanded: 0,
    suppressAggFuncInHeader: true,
    onColumnRowGroupChanged: (event) => capRowGroupDepth(event.api, rowGrouping.max_depth),
    getChildCount: (data: TableRow) => Number(data[GROUP_ROW_CHILD_COUNT] ?? 0),
    autoGroupColumnDef: {
      headerName: t('table.grouping.groupColumn'),
      minWidth: GROUP_COLUMN_MIN_WIDTH,
      cellRendererParams: {
        innerRenderer: (params: ICellRendererParams<TableRow>) =>
          String(
            params.node.data?.[GROUP_ROW_KEY] === NULL_GROUP_KEY
              ? t('table.grouping.unassigned')
              : (params.node.data?.[GROUP_ROW_LABEL] ?? params.value ?? ''),
          ),
      },
    },
  }
}

/** Drops the most recently added grouped columns beyond the configured depth. */
function capRowGroupDepth(api: GridApi, maxDepth: number): void {
  const extra = api.getRowGroupColumns().slice(maxDepth)
  if (extra.length > 0) {
    api.removeRowGroupColumns(extra)
  }
}

/** Extra `ColDef` props of one data column while grouping is on: draggable to the panel, aggregated on group rows. */
export function resolveGroupingColumnProps(
  column: TableColumn,
  rowGrouping: TableRowGroupingConfig | undefined,
): Partial<ColDef> {
  if (!rowGrouping?.enabled) {
    return {}
  }
  return {
    enableRowGroup: column.groupable === true && rowGrouping.columns.includes(column.id),
    ...(column.aggFunc ? { initialAggFunc: column.aggFunc, allowedAggFuncs: [column.aggFunc] } : {}),
  }
}

/**
 * Wraps a domain cell renderer so a group row only paints it on aggregated
 * columns: the other cells of a group row hold no value the renderer could read.
 */
export function guardGroupRowRenderer(
  column: TableColumn,
  renderer: (params: ICellRendererParams) => React.ReactNode,
): (params: ICellRendererParams) => React.ReactNode {
  return (params) => (params.node?.group && !column.aggFunc ? null : renderer(params))
}

/** Stable row id: a leaf by its `id`, a group by the path of keys leading to it. */
export function resolveRowId(params: GetRowIdParams<TableRow>): string {
  if (params.data[GROUP_ROW_FLAG] === true) {
    return `group:${[...(params.parentKeys ?? []), String(params.data[GROUP_ROW_KEY])].join('/')}`
  }
  return String(params.data.id)
}
