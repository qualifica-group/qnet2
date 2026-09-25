import { useCallback, useImperativeHandle, useMemo, useState, type Ref } from 'react'
import type { GridApi, GridReadyEvent } from 'ag-grid-community'
import { useAbilities } from '@/features/auth/use-abilities'
import { createSsrmDatasource } from '@/features/table/ssrm-datasource'
import { useTableToolbarState, type TableToolbarState } from '@/features/table/use-table-toolbar-state'
import { useTableAdvancedFilters } from '@/features/table/advanced-filters/use-table-advanced-filters'
import type { UseAdvancedFiltersResult } from '@/features/table/advanced-filters/use-advanced-filters'
import type { AdvancedFilterDescriptor, AdvancedFilterValues } from '@/features/table/advanced-filters/types'
import { useBulkActionsSlot, type BulkAction, type TableSelection } from '@/features/table/use-bulk-actions-slot'
import { EMPTY_FILTER_MODEL } from '@/features/table/use-table-layout-persistence'
import { useCustomFilterState, type UseCustomFilterStateResult } from '@/features/table/custom-filters/use-custom-filter-state'
import type { TableConfig, TableRowsAggregates } from '@/features/table/types'

/** Imperative handle exposed by the generic table to its domain adapter. */
export interface TableViewHandle {
  /** Purges and reloads the SSRM cache (call after a CRUD mutation). */
  refresh: () => void
  /** Clears the current row selection (call after a bulk action succeeds). */
  clearSelection: () => void
}

export interface UseTableViewGridStateArgs {
  domain: string
  productCategoryId?: number
  opportunityId?: number
  quoteId?: number
  onRowCountChanged?: (count: number | null) => void
  getBulkActions?: (selection: TableSelection) => BulkAction[]
  disableBuiltinDelete?: boolean
  advancedFiltersOverride?: AdvancedFilterValues | null
  onAdvancedFiltersOverrideCleared?: () => void
  treeData?: boolean
  /** The loaded config (or `undefined` while pending/erroring). */
  config: TableConfig | undefined
}

export interface UseTableViewGridStateResult {
  canExport: boolean
  exportOpen: boolean
  setExportOpen: (open: boolean) => void
  canPublishFilterViews: boolean
  initialFilterModel: Record<string, unknown>
  gridApi: GridApi | null
  handleGridReady: (event: GridReadyEvent) => void
  /** Purges and reloads the SSRM cache; shared with layout/filter persistence. */
  refreshGrid: () => void
  /** The domain's global quick-search allow-list (spec 0009), needed by the filters-state hook to build the placeholder. */
  searchable: string[]
  searchEnabled: boolean
  toolbar: TableToolbarState
  bulkActionsSlot: ReturnType<typeof useBulkActionsSlot>['bulkActionsSlot']
  enableSelection: boolean
  handleSelectionChanged: (selection: TableSelection) => void
  handleRowCountChanged: (count: number) => void
  /** The domain's active custom filter (spec 0158), needed by the filters-state hook too. */
  customFilterState: UseCustomFilterStateResult
  advancedFilterDescriptors: AdvancedFilterDescriptor[]
  advancedFilters: UseAdvancedFiltersResult
  aggregates: TableRowsAggregates | undefined
  datasource: ReturnType<typeof createSsrmDatasource>
}

/**
 * Owns the grid API, the SSRM datasource and every piece of state that feeds
 * it directly: export/publish gates, bulk selection, the unified toolbar and
 * the advanced-filter catalog. Paired with `use-table-view-filters-state.ts`
 * (which depends on this hook's output) by `use-table-view-controller.ts` —
 * split purely to keep each file under the engineering.md §6 size budget.
 */
export function useTableViewGridState(
  args: UseTableViewGridStateArgs,
  ref: Ref<TableViewHandle>,
): UseTableViewGridStateResult {
  const {
    domain,
    productCategoryId,
    opportunityId,
    quoteId,
    onRowCountChanged,
    getBulkActions,
    disableBuiltinDelete,
    advancedFiltersOverride,
    onAdvancedFiltersOverrideCleared,
    treeData,
    config,
  } = args

  // Export is generic (spec 0014): TableView owns the grid api, so it gates,
  // builds and mounts the export affordance itself — no per-module wiring.
  const { can } = useAbilities()
  const canExport = can(`${domain}.export`)
  const [exportOpen, setExportOpen] = useState(false)
  // Spec 0158 D-3: `shared` visibility on a filter view of ANY domain
  // requires this one, domain-agnostic permission.
  const canPublishFilterViews = can('table-filter-views.publish')

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
    disableBuiltinDelete,
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

  // The domain's active custom filter (spec 0158), in memory only. Built
  // BEFORE `useTableAdvancedFilters` so its `notifyExternalChange` can be
  // wired into the panel's `onApplied` below without a callback cycle
  // between the two hooks (see `use-table-custom-filters.ts`).
  const customFilterState = useCustomFilterState()

  // The domain's advanced filter catalog (spec 0032); empty ⇒ the toolbar
  // hides the toggle entirely and the panel never mounts. Draft/applied
  // state, dependencies and persistence are owned by the dedicated hook;
  // Apply/Reset purge-reload the grid exactly once via `refreshGrid` — and,
  // since a normal Apply/Reset "wins" over an active custom filter (spec
  // 0158 D-2), deactivate it first.
  const { descriptors: advancedFilterDescriptors, filters: advancedFilters } =
    useTableAdvancedFilters({
      domain,
      descriptors: config?.advancedFilters,
      applied: config?.appliedAdvancedFilters,
      onApplied: () => {
        customFilterState.notifyExternalChange()
        refreshGrid()
      },
      override: advancedFiltersOverride,
      onOverrideCleared: onAdvancedFiltersOverrideCleared,
    })

  // The domain's own aggregate figures over the WHOLE filtered set (spec
  // 0156 D-3), refreshed on every SSRM response; `undefined` for a domain
  // with no `aggregates()` override. Only read when the caller supplies
  // `renderFooter`, but always tracked — an unused `useState` here costs
  // nothing and keeps the datasource memo below a single shape either way.
  const [aggregates, setAggregates] = useState<TableRowsAggregates | undefined>(undefined)

  // One datasource instance per domain; stable across re-renders. The current
  // search term and applied advanced filters are read lazily via getters, so
  // typing/toggling never rebuilds it (the grid is purge-reloaded instead).
  const datasource = useMemo(
    () =>
      createSsrmDatasource(domain, {
        getSearch: toolbar.getSearchTerm,
        getAdvancedFilters: advancedFilters.getApplied,
        getCustomFilterRules: customFilterState.getActive,
        productCategoryId,
        opportunityId,
        quoteId,
        onAggregates: setAggregates,
        treeData,
      }),
    [
      domain,
      toolbar.getSearchTerm,
      advancedFilters.getApplied,
      customFilterState.getActive,
      productCategoryId,
      opportunityId,
      quoteId,
      treeData,
    ],
  )

  useImperativeHandle(ref, () => ({ refresh: refreshGrid, clearSelection }), [
    refreshGrid,
    clearSelection,
  ])

  return {
    canExport,
    exportOpen,
    setExportOpen,
    canPublishFilterViews,
    initialFilterModel,
    gridApi,
    handleGridReady,
    refreshGrid,
    searchable,
    searchEnabled,
    toolbar,
    bulkActionsSlot,
    enableSelection,
    handleSelectionChanged,
    handleRowCountChanged,
    customFilterState,
    advancedFilterDescriptors,
    advancedFilters,
    aggregates,
    datasource,
  }
}
