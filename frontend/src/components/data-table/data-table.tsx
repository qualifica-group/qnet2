import { useCallback, useMemo, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { AgGridReact } from 'ag-grid-react'
import {
  type ColDef,
  type ColumnMovedEvent,
  type ColumnResizedEvent,
  type ColumnVisibleEvent,
  type GetRowIdParams,
  type GridOptions,
  type GridReadyEvent,
  type GridState,
  type ICellRendererParams,
  type IServerSideDatasource,
  type ModelUpdatedEvent,
  type RowSelectionOptions,
  type SelectionChangedEvent,
} from 'ag-grid-community'
import {
  AG_GRID_LOCALE_EN,
  AG_GRID_LOCALE_IT,
} from '@ag-grid-community/locale'
import { type CellRenderer } from '@/components/data-table/column-defaults'
import {
  buildColDefs,
  DEFAULT_MIN_WIDTH,
} from '@/components/data-table/column-def-builder'
import { setupAgGrid } from '@/components/data-table/ag-grid-setup'
import {
  ACTIONS_COLUMN_ID,
  SIDE_BAR,
  SkeletonLoadingCell,
  TableEmptyOverlay,
} from '@/components/data-table/data-table-overlays'
import { buildDataTableTheme } from '@/components/data-table/data-table-theme'
import { buildRowSelectionOptions } from '@/components/data-table/row-selection'
import type { TableColumn, TableRow } from '@/features/table/types'
import { MAX_COLUMN_WIDTH } from '@/features/table/use-table-preferences'
import { useTableCellEdit } from '@/features/table/use-table-cell-edit'
import { useUiScale } from '@/features/appearance/ui-scale-context'

// Re-exported so existing domain renderer maps (`features/table/renderer-registry.ts`)
// keep importing `CellRenderer` from this module; the type itself now lives in
// `column-defaults.tsx` alongside the fallback-selection logic that uses it.
export type { CellRenderer }
// Re-exported so every existing `@/components/data-table/data-table` import
// (this module's own tests included) keeps working unchanged; the
// implementations now live in `data-table-overlays.tsx` (engineering.md §6).
export { ACTIONS_COLUMN_ID, SkeletonLoadingCell, TableEmptyOverlay }

// Register enterprise modules + license once, at module load.
setupAgGrid()

/** Renders the per-row actions cell (left-most column). */
export type RowActionsRenderer = (params: ICellRendererParams) => React.ReactNode

interface DataTableProps {
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
  onSelectionChanged?: (selection: { ids: number[]; rows: TableRow[] }) => void
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
}

/**
 * Backend-driven, reusable AG Grid wrapper.
 *
 * Stays agnostic: it translates the backend column schema into `ColDef[]`
 * (field, headerName, hide, sortable, filter) and lets callers override
 * rendering per column id and supply a row-actions renderer. It never embeds
 * domain logic or API calls — data arrives through the SSRM `datasource`.
 */
export function DataTable({
  domain,
  productCategoryId,
  columns,
  datasource,
  blockSize,
  cellRenderers,
  renderRowActions,
  actionsHeaderLabel,
  actionsColumnHasOverflow,
  onGridReady,
  onColumnStateChanged,
  initialFilterModel,
  onFilterChanged,
  onRowCountChanged,
  enableSelection,
  onSelectionChanged,
  isRowSelectable,
  masterDetail,
  detailCellRenderer,
  detailRowAutoHeight,
}: DataTableProps) {
  const { t, i18n } = useTranslation()

  // Rebuild the theme when the user's UI scale changes so the grid's absolute
  // pixel sizes track the rest of the app.
  const { factor } = useUiScale()
  const theme = useMemo(() => buildDataTableTheme(factor), [factor])

  // Owns the PATCH -> setData/revert cycle for inline cell edits (spec 0053),
  // including the note dialog for a `requires_note` value (spec 0054 D-5):
  // domain-agnostic, so it lives here rather than in a per-domain adapter.
  const { handleCellValueChanged, noteDialogSlot } = useTableCellEdit(domain, columns)

  // AG Grid's own UI strings (filter menus, set filter, column panel, context
  // menu, pagination, "Loading…"/"No Rows To Show") come from the official
  // @ag-grid-community/locale package, selected by the active app language and
  // recomputed on language change. English is the fallback for any other locale.
  const localeText = useMemo(
    () => (i18n.language.startsWith('it') ? AG_GRID_LOCALE_IT : AG_GRID_LOCALE_EN),
    [i18n.language],
  )

  const colDefs = useMemo<ColDef[]>(
    () =>
      buildColDefs({
        domain,
        productCategoryId,
        columns,
        cellRenderers,
        renderRowActions,
        actionsHeaderLabel,
        actionsColumnHasOverflow,
        masterDetail,
        t,
      }),
    [
      domain,
      productCategoryId,
      columns,
      cellRenderers,
      renderRowActions,
      actionsHeaderLabel,
      actionsColumnHasOverflow,
      masterDetail,
      t,
    ],
  )

  // Persist only USER-driven layout changes. AG Grid also emits these events for
  // its own programmatic updates (`api`, e.g. when we apply new columnDefs) and
  // for flex re-layout (`flex`); excluding them prevents a save-then-rebuild loop.
  // Resize/move are debounced upstream and only acted on when `finished`.
  const handleColumnResized = useCallback(
    (event: ColumnResizedEvent) => {
      if (event.finished && event.source !== 'api' && event.source !== 'flex') {
        onColumnStateChanged?.()
      }
    },
    [onColumnStateChanged],
  )
  const handleColumnMoved = useCallback(
    (event: ColumnMovedEvent) => {
      if (event.finished && event.source !== 'api') {
        onColumnStateChanged?.()
      }
    },
    [onColumnStateChanged],
  )
  const handleColumnVisible = useCallback(
    (event: ColumnVisibleEvent) => {
      if (event.source !== 'api') {
        onColumnStateChanged?.()
      }
    },
    [onColumnStateChanged],
  )

  // Report the grid's total known row count on every model update. For the SSRM
  // this is the `rowCount` the datasource last reported (the query total), which
  // is exactly the "N rows" figure the toolbar shows.
  const handleModelUpdated = useCallback(
    (event: ModelUpdatedEvent) => {
      onRowCountChanged?.(event.api.getDisplayedRowCount())
    },
    [onRowCountChanged],
  )

  // Required by SSRM for stable row identity across block reloads, and by the
  // selection feature to track selected rows by id rather than row index.
  const getRowId = useCallback(
    (params: GetRowIdParams<TableRow>) => String(params.data.id),
    [],
  )

  // SSRM stores a header "select all" as a selection-state flag rather than
  // individually-toggled nodes, so `getSelectedRows()` returns empty on that
  // path (only single-row toggles populate it). Walking the loaded nodes and
  // reading `isSelected()` yields the concrete ids for both single-row and
  // select-all, and is naturally scoped to the loaded/current page — matching
  // the bulk-delete contract (delete by explicit id, current page only).
  const handleSelectionChanged = useCallback(
    (event: SelectionChangedEvent<TableRow>) => {
      const ids: number[] = []
      const rows: TableRow[] = []
      event.api.forEachNode((node) => {
        if (node.isSelected() && node.data) {
          ids.push(node.data.id)
          rows.push(node.data)
        }
      })
      onSelectionChanged?.({ ids, rows })
    },
    [onSelectionChanged],
  )

  // The row-selection config, gated on `isRowSelectable` when the caller
  // supplies one (spec 0048 AC-040) — see `buildRowSelectionOptions`. Omitted
  // entirely, every row stays selectable (pre-existing behavior).
  const rowSelection = useMemo<RowSelectionOptions<TableRow> | undefined>(
    () => (enableSelection ? buildRowSelectionOptions(isRowSelectable) : undefined),
    [enableSelection, isRowSelectable],
  )

  // Apply the saved filters once, at grid creation, so the first SSRM request is
  // already filtered. Omitted when empty so a clean table starts unfiltered.
  const initialState = useMemo<GridState | undefined>(
    () =>
      initialFilterModel && Object.keys(initialFilterModel).length > 0
        ? { filter: { filterModel: initialFilterModel } }
        : undefined,
    [initialFilterModel],
  )

  const gridOptions = useMemo<GridOptions<TableRow>>(
    () => ({
      rowModelType: 'serverSide',
      serverSideDatasource: datasource,
      sideBar: SIDE_BAR,
      // Universal custom field columns (spec 0021) use a dotted id
      // (`custom.<key>`) as a FLAT row key, not a nested path. AG Grid's default
      // dot-notation would otherwise read `field: 'custom.<key>'` as
      // `row.data.custom.<key>` and render every custom column blank.
      suppressFieldDotNotation: true,
      // Reveal the header filter and column-menu buttons only while the pointer
      // is over the column header; the v35 default keeps them always visible.
      suppressMenuHide: false,
      cacheBlockSize: blockSize,
      // Show a column-shaped skeleton while each SSRM block loads: opting out of
      // the full-width loading row makes AG Grid render `loadingCellRenderer`
      // per cell, so the placeholder mirrors the real column layout.
      suppressServerSideFullWidthLoadingRow: true,
      loadingCellRenderer: SkeletonLoadingCell,
      // Friendlier empty state than the stock "No Rows To Show" text.
      noRowsOverlayComponent: TableEmptyOverlay,
      // Render a full page of loading rows on the initial load too: without this,
      // SSRM shows a single loading row until the first response reveals the row
      // count. Seeding the count with the page size makes the grid paint
      // `blockSize` skeleton rows immediately.
      serverSideInitialRowCount: blockSize,
      pagination: true,
      paginationPageSize: blockSize,
      paginationPageSizeSelector: [blockSize, blockSize * 2, blockSize * 4],
      defaultColDef: {
        resizable: true,
        flex: 1,
        minWidth: DEFAULT_MIN_WIDTH,
        // Stop the drag where persistence stops: the server caps a saved width at
        // MAX_COLUMN_WIDTH, so without this the user can widen a column past the
        // cap and see the layout snap back on the next load.
        maxWidth: MAX_COLUMN_WIDTH,
      },
      // Stable row ids are required for SSRM selection to survive block
      // reloads; only wired when selection is actually enabled.
      getRowId: enableSelection ? getRowId : undefined,
      rowSelection,
      // The checkbox column defaults to unpinned: with the actions column pinned
      // left it would otherwise land to its RIGHT, in the scrollable section.
      // Pinning it keeps it first (its `lockPosition: 'left'` orders it before
      // the actions column inside the pinned-left section).
      selectionColumnDef: { pinned: 'left' },
      // Inline cell editing (spec 0053, D-9): single click, Enter/blur commits.
      singleClickEdit: true,
      stopEditingWhenCellsLoseFocus: true,
      onCellValueChanged: handleCellValueChanged,
      // Master/Detail (spec 0059 D-4), opt-in and additive: `masterDetail`
      // defaults to `false`, so every domain that does not pass it sees no
      // change at all. `detailCellRenderer` is wrapped the same way
      // `renderRowActions` is above (a plain function AG Grid treats as a
      // component).
      masterDetail,
      detailCellRenderer: detailCellRenderer
        ? (params: ICellRendererParams<TableRow>) => detailCellRenderer(params)
        : undefined,
      detailRowAutoHeight,
    }),
    [
      datasource,
      blockSize,
      enableSelection,
      getRowId,
      rowSelection,
      handleCellValueChanged,
      masterDetail,
      detailCellRenderer,
      detailRowAutoHeight,
    ],
  )

  return (
    // Fills its parent so the toolbar block controls the height (fixed in the
    // normal layout, flex-1 in fullscreen — spec 0009).
    <div className="h-full min-h-0 w-full">
      <AgGridReact
        theme={theme}
        columnDefs={colDefs}
        localeText={localeText}
        initialState={initialState}
        onGridReady={onGridReady}
        onColumnResized={handleColumnResized}
        onColumnMoved={handleColumnMoved}
        onColumnVisible={handleColumnVisible}
        onFilterChanged={onFilterChanged}
        onModelUpdated={handleModelUpdated}
        onSelectionChanged={enableSelection ? handleSelectionChanged : undefined}
        {...gridOptions}
      />
      {noteDialogSlot}
    </div>
  )
}
