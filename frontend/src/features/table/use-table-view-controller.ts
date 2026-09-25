import type { Ref } from 'react'
import type { TFunction } from 'i18next'
import type { GridApi, GridReadyEvent } from 'ag-grid-community'
import type { UseAdvancedFiltersResult } from '@/features/table/advanced-filters/use-advanced-filters'
import type { AdvancedFilterDescriptor, AdvancedFilterValues } from '@/features/table/advanced-filters/types'
import type { BulkAction, TableSelection, useBulkActionsSlot } from '@/features/table/use-bulk-actions-slot'
import { createRowActionsRenderer, type RowActionHandler, type RowActionsOptions } from '@/features/table/row-actions'
import type { TableConfigScope } from '@/features/table/use-table-config'
import type { TableToolbarState } from '@/features/table/use-table-toolbar-state'
import type { UseTableCustomFiltersResult } from '@/features/table/custom-filters/use-table-custom-filters'
import type { UseActiveFilterChipsResult } from '@/features/table/custom-filters/use-active-filter-chips'
import { createSsrmDatasource } from '@/features/table/ssrm-datasource'
import { useViewportTableHeight } from '@/features/table/use-viewport-table-height'
import { useTableViewGridState, type TableViewHandle } from '@/features/table/use-table-view-grid-state'
import { useTableViewFiltersState } from '@/features/table/use-table-view-filters-state'
import type { TableConfig, TableRowsAggregates } from '@/features/table/types'

export type { TableViewHandle } from '@/features/table/use-table-view-grid-state'

/** The subset of `TableViewProps` this controller needs (everything used only for pass-through JSX stays out — see `table-view.tsx`). */
export interface TableViewControllerArgs extends RowActionsOptions {
  domain: string
  scope?: TableConfigScope
  productCategoryId?: number
  opportunityId?: number
  quoteId?: number
  onRowCountChanged?: (count: number | null) => void
  onAction: RowActionHandler
  getBulkActions?: (selection: TableSelection) => BulkAction[]
  disableBuiltinDelete?: boolean
  advancedFiltersOverride?: AdvancedFilterValues | null
  onAdvancedFiltersOverrideCleared?: () => void
  treeData?: boolean
  /** The loaded config (or `undefined` while pending/erroring) — fetched by the caller so its narrowing stays local to `table-view.tsx`. */
  config: TableConfig | undefined
  /** Refetches the config; awaited by the "reset to default" flows. */
  refetchConfig: () => Promise<unknown>
  t: TFunction
}

export interface TableViewControllerResult {
  canExport: boolean
  exportOpen: boolean
  setExportOpen: (open: boolean) => void
  canPublishFilterViews: boolean
  initialFilterModel: Record<string, unknown>
  gridApi: GridApi | null
  handleGridReady: (event: GridReadyEvent) => void
  searchEnabled: boolean
  toolbar: TableToolbarState
  bulkActionsSlot: ReturnType<typeof useBulkActionsSlot>['bulkActionsSlot']
  enableSelection: boolean
  handleSelectionChanged: (selection: TableSelection) => void
  handleRowCountChanged: (count: number) => void
  advancedFilterDescriptors: AdvancedFilterDescriptor[]
  advancedFilters: UseAdvancedFiltersResult
  aggregates: TableRowsAggregates | undefined
  datasource: ReturnType<typeof createSsrmDatasource>
  layoutVersion: number
  isCustomized: boolean
  isFilterCustomized: boolean
  setFiltersCustomizedLocally: (hasFilters: boolean) => void
  handleColumnStateChanged: () => void
  handleGridFilterChanged: () => void
  handleResetLayout: () => Promise<void>
  handleResetFilters: () => Promise<void>
  resettingLayout: boolean
  resettingFilters: boolean
  customFilters: UseTableCustomFiltersResult
  filterChips: UseActiveFilterChipsResult
  searchPlaceholder: string
  renderRowActions: ReturnType<typeof createRowActionsRenderer> | undefined
  gridContainerRef: ReturnType<typeof useViewportTableHeight>['containerRef']
  gridHeight: number | null
}

/**
 * Composes `use-table-view-grid-state.ts` (grid API, SSRM datasource, toolbar,
 * bulk selection, advanced filters) with `use-table-view-filters-state.ts`
 * (layout/filter persistence, custom filters, search placeholder, row-actions
 * renderer, viewport-fit height) — the latter consumes the former's output.
 * `TableView`'s only entry point into all of it (engineering.md §6).
 */
export function useTableViewController(
  args: TableViewControllerArgs,
  ref: Ref<TableViewHandle>,
): TableViewControllerResult {
  const {
    domain,
    scope,
    productCategoryId,
    opportunityId,
    quoteId,
    onRowCountChanged,
    onAction,
    isBusy,
    decorateRow,
    iconMap,
    labeledActions,
    getBulkActions,
    disableBuiltinDelete,
    advancedFiltersOverride,
    onAdvancedFiltersOverrideCleared,
    treeData,
    config,
    refetchConfig,
    t,
  } = args

  // Step 1: grid API, SSRM datasource and everything that feeds it directly.
  const grid = useTableViewGridState(
    {
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
    },
    ref,
  )

  // Step 2: layout/filter persistence and presentation, downstream of Step 1.
  const filters = useTableViewFiltersState({
    domain,
    scope,
    config,
    refetchConfig,
    gridApi: grid.gridApi,
    refreshGrid: grid.refreshGrid,
    initialFilterModel: grid.initialFilterModel,
    customFilterState: grid.customFilterState,
    advancedFilterDescriptors: grid.advancedFilterDescriptors,
    advancedFilters: grid.advancedFilters,
    searchable: grid.searchable,
    searchInput: grid.toolbar.searchInput,
    setSearchInput: grid.toolbar.setSearchInput,
    fullscreen: grid.toolbar.fullscreen,
    onAction,
    isBusy,
    decorateRow,
    iconMap,
    labeledActions,
    t,
  })

  return {
    canExport: grid.canExport,
    exportOpen: grid.exportOpen,
    setExportOpen: grid.setExportOpen,
    canPublishFilterViews: grid.canPublishFilterViews,
    initialFilterModel: grid.initialFilterModel,
    gridApi: grid.gridApi,
    handleGridReady: grid.handleGridReady,
    searchEnabled: grid.searchEnabled,
    toolbar: grid.toolbar,
    bulkActionsSlot: grid.bulkActionsSlot,
    enableSelection: grid.enableSelection,
    handleSelectionChanged: grid.handleSelectionChanged,
    handleRowCountChanged: grid.handleRowCountChanged,
    advancedFilterDescriptors: grid.advancedFilterDescriptors,
    advancedFilters: grid.advancedFilters,
    aggregates: grid.aggregates,
    datasource: grid.datasource,
    layoutVersion: filters.layoutVersion,
    isCustomized: filters.isCustomized,
    isFilterCustomized: filters.isFilterCustomized,
    setFiltersCustomizedLocally: filters.setFiltersCustomizedLocally,
    handleColumnStateChanged: filters.handleColumnStateChanged,
    handleGridFilterChanged: filters.handleGridFilterChanged,
    handleResetLayout: filters.handleResetLayout,
    handleResetFilters: filters.handleResetFilters,
    resettingLayout: filters.resettingLayout,
    resettingFilters: filters.resettingFilters,
    customFilters: filters.customFilters,
    filterChips: filters.filterChips,
    searchPlaceholder: filters.searchPlaceholder,
    renderRowActions: filters.renderRowActions,
    gridContainerRef: filters.gridContainerRef,
    gridHeight: filters.gridHeight,
  }
}
