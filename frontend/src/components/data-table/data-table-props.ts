import type { ReactNode } from 'react'
import type { GridReadyEvent, ICellRendererParams, IServerSideDatasource } from 'ag-grid-community'
import type { CellRenderer } from '@/components/data-table/column-defaults'
import type { TableColumn, TableRow, TableRowGroupingConfig, TableRowId } from '@/features/table/types'
import type { CellCommitInterceptor } from '@/features/table/use-table-cell-edit'

/** Renders the per-row actions cell (left-most column). */
export type RowActionsRenderer = (params: ICellRendererParams) => ReactNode

export interface DataTableProps {
  /**
   * Domain key selecting the server-side table definition (e.g. "users").
   * The only domain-specific input the wrapper needs: it feeds the Set
   * Filter's async values-callback (POST /tables/{domain}/values), nothing
   * else about the domain leaks in.
   */
  domain: string
  /**
   * The selected Product Category scope (spec 0064, request-management's
   * category tabs), forwarded to the Set Filter's async values callback
   * (`POST /tables/{domain}/values`) so an `attr.<code>` column's distinct
   * values resolve against the right category. Absent for every other domain.
   */
  productCategoryId?: number
  /** Root Product Category tab (spec 0337), forwarded to the Set Filter values callback. */
  productCategoryBranchId?: number
  /**
   * The Opportunity row-set scope (spec 0067 D-1, the Opportunity detail's
   * Quotes panel), forwarded to the Set Filter's async values callback
   * (`POST /tables/{domain}/values`) so an `opportunityId`-scoped column's
   * distinct values resolve against that Opportunity's Offerte only. Absent
   * for every other domain.
   */
  opportunityId?: number
  /**
   * The Contract's Offerta row-set scope (spec 0095 D-8, the Contract
   * detail's Commesse tab), forwarded to the Set Filter's async values
   * callback the same way `opportunityId` is. Absent for every domain but
   * `work-orders`.
   */
  quoteId?: number
  /** Row-set scope to one Anagrafica (spec 0199); a no-op for every domain but `opportunities`, `quotes`, `work-orders`, `tasks`. */
  registryId?: number
  /** Row-set scope to one Referente (spec 0336); a no-op for every domain but `commission-configurations`. */
  referentId?: number
  /** Backend-driven column schema. */
  columns: TableColumn[]
  /** SSRM datasource feeding the grid. */
  datasource: IServerSideDatasource
  /** Default page/block size from the backend config. */
  blockSize: number
  /** Optional map columnId → custom cell renderer (badges, links, formatting). */
  cellRenderers?: Record<string, CellRenderer>
  /** Optional renderer for the leading row-actions column. */
  renderRowActions?: RowActionsRenderer
  /** i18n key for the action column header. */
  actionsHeaderLabel?: string
  /**
   * Whether the domain can show the overflow (three-dots) button — i.e. its
   * action catalog exceeds the inline limit. Widens the actions column just
   * enough to fit the extra control; otherwise the column keeps its default
   * narrow width.
   */
  actionsColumnHasOverflow?: boolean
  /** Explicit actions-column width (labeled row actions); overrides `actionsColumnHasOverflow`. */
  actionsColumnWidth?: number
  /**
   * Fired once the grid is ready. Exposes the AG Grid event so callers can keep
   * a reference to the grid API (e.g. to refresh SSRM blocks after a mutation).
   * The wrapper stays agnostic: it forwards the event without acting on it.
   */
  onGridReady?: (event: GridReadyEvent) => void
  /**
   * Fired (debounced by the caller) when the USER changes the column layout —
   * reorder, resize, or show/hide. Programmatic (`api`) and flex-driven changes
   * are filtered out here so they never trigger a persist loop. The wrapper does
   * not read or store the state; the caller pulls it from the grid API.
   */
  onColumnStateChanged?: () => void
  /**
   * The user's saved filterModel, applied once at grid creation so the filters
   * (and the first SSRM request) reflect the persisted state on mount. Passed
   * through `initialState` — a later value has no effect without a remount, which
   * is exactly how the caller drives a filter reset (bump the grid `key`).
   */
  initialFilterModel?: Record<string, unknown>
  /**
   * Fired when the user changes a column filter. The wrapper does not read or
   * store the filter model; the caller pulls it from the grid API (debounced) to
   * persist it. Forwarded verbatim from AG Grid's `onFilterChanged`.
   */
  onFilterChanged?: () => void
  /**
   * Fired with the grid's total known row count whenever the model updates, so
   * the toolbar can show a live "N rows" counter (spec 0009).
   */
  onRowCountChanged?: (count: number) => void
  /**
   * Turns on multi-row checkbox selection (header "select all" scoped to the
   * current page). Off by default so domains without a bulk action see no
   * behavior change.
   */
  enableSelection?: boolean
  /**
   * Fired with the currently-selected rows' ids AND their full row data
   * whenever the selection changes. Only wired when `enableSelection` is
   * true. `rows` (spec 0048 AC-031) lets a domain adapter read a field off
   * the selection (e.g. the Lead's Sede) without a second fetch — omit it if
   * a caller only needs ids.
   */
  onSelectionChanged?: (selection: { ids: TableRowId[]; rows: TableRow[] }) => void
  /**
   * Optional per-row predicate gating which rows can be checked for bulk
   * selection (e.g. spec 0048 AC-040: a Lead already assigned to an operator
   * is not selectable). Domain-agnostic and additive: omitted, every row
   * stays selectable exactly as before, so no other domain regresses.
   */
  isRowSelectable?: (row: TableRow) => boolean
  /**
   * Enables AG Grid's Master/Detail (spec 0059 D-4): a row expands into a
   * custom detail panel instead of the row-grouping tree AG Grid normally
   * uses for this control. Additive opt-in — every other domain leaves it
   * `undefined` and sees no behavior change (`masterDetail` defaults to
   * `false`). The pattern stays isolated to the one feature that needs it
   * (`rewarded-referents`); this wrapper does not generalize it further.
   */
  masterDetail?: boolean
  /** The detail panel's renderer. Required (by the caller) whenever `masterDetail` is true. */
  detailCellRenderer?: (params: ICellRendererParams<TableRow>) => ReactNode
  /** Detail row grows to fit its content instead of a fixed pixel height. */
  detailRowAutoHeight?: boolean
  /** Per-domain cell-commit interception (spec 0156 D-8), forwarded verbatim to `useTableCellEdit`. */
  interceptCellCommit?: CellCommitInterceptor
  /**
   * Server-side tree data (spec 0157 D-1), opt-in and off by default: the
   * FIRST column becomes the tree/expand column (rendered through AG Grid's
   * own `autoGroupColumnDef`, its flat `ColDef` hidden so it never shows
   * twice — see `treeGroupColumnId` in `column-def-builder.ts`).
   * `isServerSideGroup`/`getServerSideGroupKey` read the row's own
   * `has_subtasks`/`id` — a domain-agnostic pair the caller's datasource must
   * itself send `tree`/`treeParentId` for (see `ssrm-datasource.ts`). Every
   * domain but `tasks` leaves this `false`/omitted and sees no change.
   */
  treeData?: boolean
  /** Opt-in server-side row grouping (spec 0197): row group panel, groupable/aggregated columns. Absent or disabled ⇒ no change. */
  rowGrouping?: TableRowGroupingConfig
}
