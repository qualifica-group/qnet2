import {
  forwardRef,
  useCallback,
  useImperativeHandle,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { useTranslation } from 'react-i18next'
import type { GridApi, GridReadyEvent, ICellRendererParams } from 'ag-grid-community'
import { Download } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DropdownMenuItem } from '@/components/ui/dropdown-menu'
import { ACTIONS_COLUMN_ID, DataTable } from '@/components/data-table/data-table'
import { useAbilities } from '@/features/auth/use-abilities'
import { createSsrmDatasource } from '@/features/table/ssrm-datasource'
import { SavedViewsSlot } from '@/features/table/saved-views-slot'
import { TableToolbar } from '@/features/table/table-toolbar'
import { useTableToolbarState } from '@/features/table/use-table-toolbar-state'
import { AdvancedFilterPanel, ADVANCED_FILTER_PANEL_ANIMATION } from '@/features/table/advanced-filters/advanced-filter-panel'
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible'
import { useTableAdvancedFilters } from '@/features/table/advanced-filters/use-table-advanced-filters'
import { useBulkActionsSlot, type BulkAction, type TableSelection } from '@/features/table/use-bulk-actions-slot'
import { ExportDialog } from '@/features/exports/export-dialog'
import {
  createRowActionsRenderer,
  INLINE_ACTION_LIMIT,
  type RowActionHandler,
  type RowActionsOptions,
} from '@/features/table/row-actions'
import { useTableConfig, type TableConfigScope } from '@/features/table/use-table-config'
import { EMPTY_FILTER_MODEL, useTableLayoutPersistence } from '@/features/table/use-table-layout-persistence'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { TableRow, TableRowScope } from '@/features/table/types'

/** Imperative handle exposed by the generic table to its domain adapter. */
export interface TableViewHandle {
  /** Purges and reloads the SSRM cache (call after a CRUD mutation). */
  refresh: () => void
  /** Clears the current row selection (call after a bulk action succeeds). */
  clearSelection: () => void
}

interface TableViewProps extends RowActionsOptions {
  /** Domain key selecting the server-side table definition (e.g. "users"). */
  domain: string
  /**
   * Narrows the domain's config/rows/Set-Filter-values to one scope (spec
   * 0064: request-management's Product Category tabs). Omitted ⇒ today's
   * unscoped behavior for every other domain. The adapter is expected to key
   * its own `<TableView>` element by the scope (e.g. by category id) so a
   * scope change remounts the whole table and restarts every client-side
   * state (search, filters, layout) from the fresh config's defaults (D-4) —
   * this component does not react to a scope prop CHANGE on its own.
   */
  scope?: TableConfigScope
  /**
   * Narrows the domain's rows/values/export requests to one PARENT RECORD
   * (spec 0067 D-1: the Opportunity detail's Quotes panel). Distinct from
   * `scope` above: `scope` selects a config SHAPE and enters the config's
   * query key; `rowScope` selects a ROW SET and never enters any query key,
   * because the config is identical scoped or not (D-1). Read once as a
   * primitive (`rowScope?.opportunityId`), same precaution as `scope`
   * documented above — a caller may pass a fresh object literal every
   * render. Omitted ⇒ today's unscoped behavior for every domain.
   */
  rowScope?: TableRowScope
  /**
   * Reports the grid's live total row count to the caller (spec 0067 D-9),
   * e.g. so a panel header can show its own counter without a separate
   * count query. Composes alongside the toolbar's own "N rows" counter — it
   * does not replace it.
   */
  onRowCountChanged?: (count: number | null) => void
  /** Per-domain custom cell renderers, keyed by column id. Optional. */
  renderers?: TableRendererMap
  /**
   * Handler invoked when a row action fires. The generic table only renders the
   * affordance (crossing `row.actions` with the catalog); the concrete behavior
   * (open sheet, run delete, …) belongs to the domain adapter.
   */
  onAction: RowActionHandler
  /**
   * Import action, threaded through to the toolbar's `importSlot` (spec 0012).
   * The adapter owns the permission gate (`<Can>`) and the dialog; TableView
   * only forwards the node.
   */
  importSlot?: ReactNode
  /**
   * Optional per-row predicate gating which rows can be checked for bulk
   * selection (spec 0048 AC-040, e.g. a Lead already assigned is not
   * selectable). Forwarded verbatim to `DataTable`; omitted, every row stays
   * selectable.
   */
  isRowSelectable?: (row: TableRow) => boolean
  /**
   * Extra bulk action(s) merged into the single "Actions" dropdown alongside
   * the built-in "delete selected" whenever the selection is non-empty (spec
   * 0048 AC-041). Receives the current selection (ids AND row data — AC-031
   * needs the latter) and returns action descriptors; the domain adapter owns
   * everything about each action (dialog, mutation, permission gate) —
   * TableView only reserves the slot and enables the checkbox column when
   * this is supplied.
   */
  getBulkActions?: (selection: TableSelection) => BulkAction[]
  /**
   * Enables AG Grid's Master/Detail (spec 0059 D-4), forwarded verbatim to
   * `DataTable`. Additive opt-in: omitted, every other domain is unaffected.
   * The pattern stays isolated to `rewarded-referents`, its one consumer.
   */
  masterDetail?: boolean
  /** The detail panel's renderer, required (by the caller) whenever `masterDetail` is true. */
  detailCellRenderer?: (params: ICellRendererParams<TableRow>) => ReactNode
  /** Detail row grows to fit its content instead of a fixed pixel height. */
  detailRowAutoHeight?: boolean
}

/**
 * Generic, domain-driven table. Given a `domain`, it loads the backend config,
 * builds the SSRM datasource, and mounts the agnostic DataTable wrapper fused
 * under a single unified toolbar (spec 0009: search + row count on the left;
 * filter toggle, saved views, options and fullscreen on the right — one bordered
 * block, no detached buttons). It owns loading/error/empty states, the SSRM
 * refresh mechanism, and the toolbar's client state (search term, floating
 * filters, fullscreen), but holds NO domain logic: custom rendering and action
 * behavior arrive entirely via props (`renderers`, `onAction`, `isBusy`,
 * `decorateRow`).
 *
 * Adding a new domain requires no change here — only a new adapter that mounts
 * this component with its `domain`, renderer map and action handler.
 */
export const TableView = forwardRef<TableViewHandle, TableViewProps>(
  function TableView(
    {
      domain,
      scope,
      rowScope,
      onRowCountChanged,
      renderers,
      onAction,
      isBusy,
      decorateRow,
      iconMap,
      importSlot,
      isRowSelectable,
      getBulkActions,
      masterDetail,
      detailCellRenderer,
      detailRowAutoHeight,
    },
    ref,
  ) {
    const { t } = useTranslation()
    // Read once as a primitive: every downstream `useMemo` below keys on this
    // value, not on the `scope`/`rowScope` object identity (a caller may pass
    // a fresh object literal every render).
    const productCategoryId = scope?.productCategoryId
    const opportunityId = rowScope?.opportunityId
    const { data: config, isPending, isError, refetch } = useTableConfig(domain, scope)

    // Export is generic (spec 0014): TableView owns the grid api, so it gates,
    // builds and mounts the export affordance itself — no per-module wiring.
    const { can } = useAbilities()
    const canExport = can(`${domain}.export`)
    const [exportOpen, setExportOpen] = useState(false)

    // The saved filterModel replayed into the grid on mount. Stable identity per
    // config load so it can seed the persisted-baseline ref below.
    const initialFilterModel = useMemo(
      () => config?.filterState ?? EMPTY_FILTER_MODEL,
      [config?.filterState],
    )

    // SSRM rows are not cached by TanStack Query, so they cannot be invalidated
    // through the queryClient. We hold the grid API and purge its server-side
    // cache directly. Stored in state (not a ref) so the imperative handle picks
    // up the API once the grid is ready.
    const [gridApi, setGridApi] = useState<GridApi | null>(null)
    const handleGridReady = useCallback((event: GridReadyEvent) => {
      setGridApi(event.api)
    }, [])

    // Purges and reloads the SSRM cache; shared by the imperative handle (used
    // by domain adapters after their own CRUD mutations) and the generic
    // bulk-delete flow below.
    const refreshGrid = useCallback(() => {
      gridApi?.refreshServerSide({ purge: true })
    }, [gridApi])

    // Bulk selection (current page only, per the SSRM select-all contract),
    // the generic bulk-delete flow and any domain-supplied extra bulk action
    // (spec 0048 AC-041) — see `use-bulk-actions-slot.ts`.
    const {
      onSelectionChanged: handleSelectionChanged,
      clearSelection,
      enableSelection,
      bulkActionsSlot,
    } = useBulkActionsSlot({
      domain,
      gridApi,
      actions: config?.actions,
      refresh: refreshGrid,
      getBulkActions,
    })

    // The domain's global quick-search allow-list (spec 0009); empty ⇒ no search
    // box. Drives both the search affordance and the placeholder labels.
    const searchable = useMemo(
      () => config?.searchable ?? [],
      [config?.searchable],
    )
    const searchEnabled = searchable.length > 0

    // Client-only toolbar state (search term + ⌘K, floating filters, fullscreen,
    // live row count), owned by a dedicated hook so this component stays a thin
    // orchestrator (engineering.md §6).
    const toolbar = useTableToolbarState({ gridApi, searchEnabled })

    // Feeds the toolbar's own "N rows" counter AND, additively, the caller's
    // `onRowCountChanged` (spec 0067 D-9) — composed here so `DataTable` keeps
    // wiring a single handler regardless of whether a caller supplies one.
    // Bound to a local identifier first: the setState setter is referentially
    // stable, but calling it as `toolbar.setRowCount(count)` (a member
    // expression callee) makes exhaustive-deps ask for the whole `toolbar`
    // object instead — a plain identifier call avoids that ambiguity.
    const { setRowCount } = toolbar
    const handleRowCountChanged = useCallback(
      (count: number) => {
        setRowCount(count)
        onRowCountChanged?.(count)
      },
      [setRowCount, onRowCountChanged],
    )

    // The domain's advanced filter catalog (spec 0032); empty ⇒ the toolbar
    // hides the toggle entirely and the panel never mounts. Draft/applied
    // state, dependencies and persistence are owned by the dedicated hook;
    // Apply/Reset purge-reload the grid exactly once via `refreshGrid`.
    const { descriptors: advancedFilterDescriptors, filters: advancedFilters } =
      useTableAdvancedFilters({
        domain,
        descriptors: config?.advancedFilters,
        applied: config?.appliedAdvancedFilters,
        onApplied: refreshGrid,
      })

    // One datasource instance per domain; stable across re-renders. The current
    // search term and applied advanced filters are read lazily via getters, so
    // typing/toggling never rebuilds it (the grid is purge-reloaded instead).
    const datasource = useMemo(
      () =>
        createSsrmDatasource(
          domain,
          toolbar.getSearchTerm,
          advancedFilters.getApplied,
          productCategoryId,
          opportunityId,
        ),
      [domain, toolbar.getSearchTerm, advancedFilters.getApplied, productCategoryId, opportunityId],
    )

    useImperativeHandle(ref, () => ({ refresh: refreshGrid, clearSelection }), [
      refreshGrid,
      clearSelection,
    ])

    // The domain's real column ids: mirrors the server's Rule::in allow-list, so
    // synthetic grid columns (row-actions, selection) are dropped from the saved
    // layout and a persist can never 422 on an unknown column id.
    const knownColumnIds = useMemo(
      () => new Set((config?.columns ?? []).map((column) => column.id)),
      [config?.columns],
    )

    // Debounced column-layout/filter persistence and their "reset to default"
    // flows (spec 0003/0009) — see `use-table-layout-persistence.ts`.
    const {
      layoutVersion,
      isCustomized,
      isFilterCustomized,
      setFiltersCustomizedLocally,
      handleColumnStateChanged,
      handleFilterChanged,
      handleResetLayout,
      handleResetFilters,
      resettingLayout,
      resettingFilters,
    } = useTableLayoutPersistence({
      domain,
      scope,
      gridApi,
      knownColumnIds,
      initialFilterModel,
      configCustomized: config?.customized ?? false,
      configFiltersCustomized: config?.filtersCustomized ?? false,
      refetchConfig: refetch,
    })

    // Placeholder built from the searchable columns' localized labels, mirroring
    // the backend allow-list (e.g. "Cerca nome/email…").
    const searchPlaceholder = useMemo(() => {
      if (!config || searchable.length === 0) {
        return t('table.search')
      }
      const labels = searchable
        .map((id) => config.columns.find((column) => column.id === id))
        .filter((column): column is NonNullable<typeof column> => Boolean(column))
        .map((column) => t(column.label))
      return t('table.searchPlaceholder', { columns: labels.join('/') })
    }, [config, searchable, t])

    const renderRowActions = useMemo(() => {
      if (!config) {
        return undefined
      }
      return createRowActionsRenderer(config.actions, onAction, {
        isBusy,
        decorateRow,
        iconMap,
      })
    }, [config, onAction, isBusy, decorateRow, iconMap])

    let content: ReactNode
    if (isPending) {
      content = (
        <div className="flex h-full flex-col gap-2 p-3">
          {Array.from({ length: 8 }).map((_, index) => (
            <Skeleton key={index} className="h-8 w-full" />
          ))}
        </div>
      )
    } else if (isError) {
      content = (
        <div className="flex h-full flex-col items-start gap-3 p-4">
          <p className="text-sm text-destructive">{t('table.loadError')}</p>
          <Button variant="outline" size="sm" onClick={() => void refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      )
    } else if (config.columns.length === 0) {
      content = (
        <p className="p-4 text-sm text-muted-foreground">
          {t('table.emptyConfig')}
        </p>
      )
    } else {
      content = (
        <DataTable
          key={layoutVersion}
          domain={domain}
          productCategoryId={productCategoryId}
          opportunityId={opportunityId}
          columns={config.columns}
          datasource={datasource}
          blockSize={config.defaultPagination.limit}
          cellRenderers={renderers}
          renderRowActions={renderRowActions}
          actionsHeaderLabel="table.actionsHeader"
          actionsColumnHasOverflow={config.actions.length > INLINE_ACTION_LIMIT}
          onGridReady={handleGridReady}
          onColumnStateChanged={handleColumnStateChanged}
          initialFilterModel={initialFilterModel}
          onFilterChanged={handleFilterChanged}
          onRowCountChanged={handleRowCountChanged}
          enableSelection={enableSelection}
          onSelectionChanged={handleSelectionChanged}
          isRowSelectable={isRowSelectable}
          masterDetail={masterDetail}
          detailCellRenderer={detailCellRenderer}
          detailRowAutoHeight={detailRowAutoHeight}
        />
      )
    }

    const savedViewsSlot = (
      <SavedViewsSlot
        domain={domain}
        gridApi={gridApi}
        config={config}
        advancedFilters={advancedFilters}
        onFilterModelApplied={setFiltersCustomizedLocally}
      />
    )

    const exportSlot = canExport ? (
      <DropdownMenuItem
        onSelect={(event) => {
          event.preventDefault()
          setExportOpen(true)
        }}
      >
        <Download aria-hidden="true" />
        {t('exports.action')}
      </DropdownMenuItem>
    ) : null

    return (
      <>
        <div
          className={cn(
            'flex min-h-0 flex-col',
            toolbar.fullscreen &&
              'fixed inset-0 z-50 bg-background/80 p-3 backdrop-blur-sm sm:p-4',
          )}
        >
          <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <TableToolbar
              searchEnabled={searchEnabled}
              searchPlaceholder={searchPlaceholder}
              searchInputRef={toolbar.searchInputRef}
              searchValue={toolbar.searchInput}
              onSearchChange={toolbar.setSearchInput}
              searchShortcut={toolbar.searchShortcut}
              rowCount={toolbar.rowCount}
              bulkActionsSlot={bulkActionsSlot}
              filtersActive={isFilterCustomized}
              onResetFilters={() => void handleResetFilters()}
              resettingFilters={resettingFilters}
              layoutCustomized={isCustomized}
              onResetLayout={() => void handleResetLayout()}
              resettingLayout={resettingLayout}
              fullscreen={toolbar.fullscreen}
              onToggleFullscreen={toolbar.toggleFullscreen}
              advancedFiltersEnabled={advancedFilterDescriptors.length > 0}
              advancedFiltersOpen={toolbar.advancedFiltersOpen}
              onToggleAdvancedFilters={toolbar.toggleAdvancedFilters}
              advancedFiltersActiveCount={advancedFilters.activeCount}
              savedViewsSlot={savedViewsSlot}
              importSlot={importSlot}
              exportSlot={exportSlot}
            />

            {advancedFilterDescriptors.length > 0 ? (
              <Collapsible open={toolbar.advancedFiltersOpen}>
                <CollapsibleContent className={ADVANCED_FILTER_PANEL_ANIMATION}>
                  <AdvancedFilterPanel descriptors={advancedFilterDescriptors} filters={advancedFilters} />
                </CollapsibleContent>
              </Collapsible>
            ) : null}

            <div
              className={cn(
                'min-h-0 w-full',
                toolbar.fullscreen ? 'flex-1' : 'h-[600px]',
              )}
            >
              {content}
            </div>
          </div>
        </div>

        {canExport && config ? (
          <ExportDialog
            domain={domain}
            open={exportOpen}
            onOpenChange={setExportOpen}
            gridApi={gridApi}
            columns={config.columns}
            actionsColumnId={ACTIONS_COLUMN_ID}
            search={toolbar.getSearchTerm()}
            opportunityId={opportunityId}
          />
        ) : null}
      </>
    )
  },
)
