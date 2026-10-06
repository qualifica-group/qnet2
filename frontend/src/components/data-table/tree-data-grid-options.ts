import type { GridOptions, ICellRendererParams } from 'ag-grid-community'
import type { CellRenderer } from '@/components/data-table/column-defaults'
import type { TFunction } from 'i18next'
import { DEFAULT_MIN_WIDTH } from '@/components/data-table/column-def-builder'
import type { TableColumn, TableRow } from '@/features/table/types'

/**
 * Server-side tree data grid options (spec 0157 D-1), split out of
 * `data-table.tsx` purely to keep that file under the engineering.md §6 size
 * budget. `isServerSideGroup`/`getServerSideGroupKey` read the row's own
 * `has_subtasks`/`id` — domain-agnostic; the caller's datasource
 * (`ssrm-datasource.ts`) is what turns an expand into a `treeParentId`
 * request. Collapsed by default (`groupDefaultExpanded: 0`).
 *
 * The group column stands in for the hidden `treeGroupColumnId` column, so
 * it renders the value with that column's own renderer (e.g. the Task title's
 * completion toggle), inside AG Grid's expand chevron.
 *
 * Returns `{}` when `treeData` is off, so spreading the result into
 * `gridOptions` is a no-op for every domain but `tasks`.
 */
export function buildTreeDataGridOptions(
  treeData: boolean | undefined,
  treeGroupColumnId: string | undefined,
  columns: TableColumn[],
  t: TFunction,
  cellRenderers?: Record<string, CellRenderer>,
): Partial<GridOptions<TableRow>> {
  if (!treeData) {
    return {}
  }

  const innerRenderer = treeGroupColumnId ? cellRenderers?.[treeGroupColumnId] : undefined

  return {
    treeData: true,
    groupDefaultExpanded: 0,
    isServerSideGroup: (data: TableRow) => Boolean(data.has_subtasks),
    getServerSideGroupKey: (data: TableRow) => String(data.id),
    autoGroupColumnDef: {
      headerName: treeGroupColumnId
        ? t(columns.find((column) => column.id === treeGroupColumnId)?.label ?? '')
        : '',
      field: treeGroupColumnId,
      minWidth: DEFAULT_MIN_WIDTH,
      cellRendererParams: {
        suppressCount: true,
        innerRenderer: innerRenderer ? (params: ICellRendererParams) => innerRenderer(params) : undefined,
      },
    },
  }
}
