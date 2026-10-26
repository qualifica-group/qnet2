import type { ColumnState, GridApi } from 'ag-grid-community'
import type { TFunction } from 'i18next'
import type { TableColumn, TableRow } from '@/features/table/types'
import type {
  ExportColumnInput,
  ExportGridState,
  ExportSortModelItem,
} from '@/features/exports/types'

interface BuildExportGridStateArgs {
  gridApi: GridApi<TableRow>
  /** The domain's column catalog: the only exportable colIds, each with its i18n label. */
  columns: TableColumn[]
  /** The applied global search term (may be empty). */
  search: string
  t: TFunction
}

/** Multi-sort directives, ordered by the grid's `sortIndex` (ascending). */
function toSortModel(columnState: ColumnState[]): ExportSortModelItem[] {
  return columnState
    .filter((state): state is ColumnState & { sort: 'asc' | 'desc' } => state.sort != null)
    .sort((a, b) => (a.sortIndex ?? 0) - (b.sortIndex ?? 0))
    .map((state) => ({ colId: state.colId, sort: state.sort }))
}

/**
 * Captures the grid's current state exactly as the export contract expects
 * it: visible columns in their display order (with the i18n-resolved header
 * that becomes the file's column title), the active sort, the active filter
 * model and the applied global search term. Pure/side-effect free so it is
 * unit-testable against a mocked `GridApi`.
 *
 * Only colIds declared in `columns` are sent: AG Grid's synthetic columns (row
 * actions, 'ag-Grid-SelectionColumn' on tables with bulk actions,
 * 'ag-Grid-AutoColumn' on grouped/tree tables) are not in the server's
 * `Rule::in` allow-list, and a single one 422s the whole export.
 */
export function buildExportGridState({
  gridApi,
  columns,
  search,
  t,
}: BuildExportGridStateArgs): ExportGridState {
  const labelById = new Map(columns.map((column) => [column.id, column.label]))
  const columnState = gridApi.getColumnState().filter((state) => labelById.has(state.colId))

  const exportColumns: ExportColumnInput[] = columnState
    .filter((state) => !state.hide)
    .map((state) => ({ colId: state.colId, header: t(labelById.get(state.colId) as string) }))

  return {
    columns: exportColumns,
    sortModel: toSortModel(columnState),
    filterModel: gridApi.getFilterModel(),
    search: search.trim(),
  }
}
