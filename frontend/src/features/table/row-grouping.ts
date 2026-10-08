import type {
  SsrmSortModelItem,
  TableGroupItem,
  TableRow,
  TableColumn,
  TableRowGroupingConfig,
} from '@/features/table/types'

/** Flag set on the row data AG Grid receives for a group row (it has no `id`/`actions`). */
export const GROUP_ROW_FLAG = '__group'
export const GROUP_ROW_KEY = '__group_key'
export const GROUP_ROW_LABEL = '__group_label'
/** Group key the server uses for rows with no value in the grouped column. */
export const NULL_GROUP_KEY = '__null__'
export const GROUP_ROW_CHILD_COUNT = '__child_count'

/** Column id AG Grid gives the single auto group column. */
const AUTO_GROUP_COLUMN_ID = 'ag-Grid-AutoColumn'

/** Row grouping options handed to the datasource; absent ⇒ the domain has no grouping. */
export interface RowGroupingOptions {
  maxDepth: number
  /** Ids of the aggFunc columns: the only non-group columns a group level can be sorted by. */
  aggColumnIds: string[]
}

/** The datasource options for the config's row_grouping block, or undefined when grouping is off. */
export function resolveRowGrouping(
  config: TableRowGroupingConfig | undefined,
  columns: TableColumn[],
): RowGroupingOptions | undefined {
  if (!config?.enabled) {
    return undefined
  }
  return {
    maxDepth: config.max_depth,
    aggColumnIds: columns.filter((column) => column.aggFunc).map((column) => column.id),
  }
}

export function isGroupItem(item: TableRow | TableGroupItem): item is TableRow & TableGroupItem {
  return item.group === true
}

export function isGroupRow(row: TableRow | undefined): boolean {
  return row?.[GROUP_ROW_FLAG] === true
}

/**
 * Maps a server group item onto the row data AG Grid expects in a grouped SSRM
 * level: the grouped column's field carries the KEY (AG Grid reads it back as
 * the next request's group key), the label/count travel in side fields, and
 * the aggregates fill the value columns.
 */
export function toGroupRow(item: TableGroupItem): TableRow {
  const aggregates: Record<string, number> = {}
  for (const [columnId, value] of Object.entries(item.aggregates)) {
    aggregates[columnId] = Number(value)
  }
  return {
    ...aggregates,
    id: item.key,
    actions: [],
    [item.column]: item.key,
    [GROUP_ROW_FLAG]: true,
    [GROUP_ROW_KEY]: item.key,
    [GROUP_ROW_LABEL]: item.label,
    [GROUP_ROW_CHILD_COUNT]: item.child_count,
  }
}

/**
 * Sort model for one grouped level. A sort on AG Grid's synthetic group column
 * becomes the id of the column grouped at that level (what the server
 * allow-list knows); at a group level only that column and the aggFunc columns
 * can order the groups, anything else would be a 422, so it is dropped.
 */
export function resolveGroupSortModel(
  sortModel: SsrmSortModelItem[],
  rowGroupCols: string[],
  level: number,
  aggColumnIds: string[],
): SsrmSortModelItem[] {
  const levelColumn = rowGroupCols[level]
  if (levelColumn === undefined) {
    return sortModel
  }
  return sortModel
    .map((item) => (item.colId === AUTO_GROUP_COLUMN_ID ? { ...item, colId: levelColumn } : item))
    .filter((item) => item.colId === levelColumn || aggColumnIds.includes(item.colId))
}
