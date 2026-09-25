import { useCallback, useMemo, type RefObject } from 'react'
import type { TFunction } from 'i18next'
import type { GridApi } from 'ag-grid-community'
import { estimateGridHeight } from '@/components/data-table/data-table-theme'
import { useUiScale } from '@/features/appearance/ui-scale-context'
import { useViewportTableHeight } from '@/features/table/use-viewport-table-height'
import type { AdvancedFilterDescriptor } from '@/features/table/advanced-filters/types'
import type { UseAdvancedFiltersResult } from '@/features/table/advanced-filters/use-advanced-filters'
import { createRowActionsRenderer, type RowActionHandler, type RowActionsOptions } from '@/features/table/row-actions'
import type { TableConfigScope } from '@/features/table/use-table-config'
import { useTableLayoutPersistence } from '@/features/table/use-table-layout-persistence'
import type { UseCustomFilterStateResult } from '@/features/table/custom-filters/use-custom-filter-state'
import { useTableCustomFilters, type UseTableCustomFiltersResult } from '@/features/table/custom-filters/use-table-custom-filters'
import { useTableFilterChips } from '@/features/table/custom-filters/use-table-filter-chips'
import type { UseActiveFilterChipsResult } from '@/features/table/custom-filters/use-active-filter-chips'
import type { TableConfig } from '@/features/table/types'

/** Stable empty column list (hoisted per `frontend.md §10`: never `?? []` inline). */
const EMPTY_COLUMNS: TableConfig['columns'] = []

export interface UseTableViewFiltersStateArgs extends RowActionsOptions {
  domain: string
  scope?: TableConfigScope
  config: TableConfig | undefined
  /** Refetches the config; awaited by the "reset to default" flow. */
  refetchConfig: () => Promise<unknown>
  gridApi: GridApi | null
  refreshGrid: () => void
  /** The same value handed to `DataTable`'s `initialFilterModel` (stable identity, seeds the persisted baseline). */
  initialFilterModel: Record<string, unknown>
  customFilterState: UseCustomFilterStateResult
  advancedFilterDescriptors: AdvancedFilterDescriptor[]
  advancedFilters: UseAdvancedFiltersResult
  searchable: string[]
  searchInput: string
  setSearchInput: (value: string) => void
  fullscreen: boolean
  onAction: RowActionHandler
  t: TFunction
}

export interface UseTableViewFiltersStateResult {
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
  gridContainerRef: RefObject<HTMLDivElement | null>
  gridHeight: number | null
}

/**
 * Owns column-layout/filter persistence, the custom filter builder and its
 * removable chip row, the search placeholder and the row-actions renderer,
 * plus the viewport-fit grid height — everything downstream of
 * `use-table-view-grid-state.ts`'s output. Split out purely to keep each file
 * under the engineering.md §6 size budget.
 */
export function useTableViewFiltersState(
  args: UseTableViewFiltersStateArgs,
): UseTableViewFiltersStateResult {
  const {
    domain,
    scope,
    config,
    refetchConfig,
    gridApi,
    refreshGrid,
    initialFilterModel,
    customFilterState,
    advancedFilterDescriptors,
    advancedFilters,
    searchable,
    searchInput,
    setSearchInput,
    fullscreen,
    onAction,
    isBusy,
    decorateRow,
    iconMap,
    labeledActions,
    t,
  } = args

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
    filterModel,
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
    refetchConfig,
  })

  // The custom filter builder's open/editing state and its `applyRules`
  // activation flow (spec 0158) — see `use-table-custom-filters.ts`.
  const customFilters = useTableCustomFilters({
    active: customFilterState,
    gridApi,
    advancedFilters,
    refreshGrid,
    onFilterModelApplied: setFiltersCustomizedLocally,
  })

  // The removable chip row below the toolbar (spec 0158 D-5) — see
  // `use-table-filter-chips.ts`.
  const filterChips = useTableFilterChips({
    gridApi,
    columns: config?.columns ?? EMPTY_COLUMNS,
    filterModel,
    advancedDescriptors: advancedFilterDescriptors,
    advancedFilters,
    search: searchInput,
    onClearSearch: () => setSearchInput(''),
    customFilters,
    refreshGrid,
    resetColumnFilters: handleResetFilters,
  })

  // A column filter change "wins" over an active custom filter (spec 0158
  // D-2); a no-op when nothing is active or the change is the custom
  // filter's own programmatic reset (suppressed, see `use-custom-filter-state.ts`).
  const handleGridFilterChanged = useCallback(() => {
    customFilterState.notifyExternalChange()
    handleFilterChanged()
  }, [customFilterState, handleFilterChanged])

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
      labeledActions,
    })
  }, [config, onAction, isBusy, decorateRow, iconMap, labeledActions])

  // Fit the grid to the screen instead of a fixed height: it takes what is
  // left of the viewport below this module's chrome, never taller than the
  // page of rows needs (no empty grid under the last row on a large screen).
  // Skipped in fullscreen, where the flex parent owns the height.
  const { factor } = useUiScale()
  const maxGridHeight = useMemo(
    () => estimateGridHeight(config?.defaultPagination.limit ?? FALLBACK_PAGE_SIZE, factor),
    [config?.defaultPagination.limit, factor],
  )
  const { containerRef: gridContainerRef, height: gridHeight } = useViewportTableHeight({
    enabled: !fullscreen,
    maxHeight: maxGridHeight,
  })

  return {
    layoutVersion,
    isCustomized,
    isFilterCustomized,
    setFiltersCustomizedLocally,
    handleColumnStateChanged,
    handleGridFilterChanged,
    handleResetLayout,
    handleResetFilters,
    resettingLayout,
    resettingFilters,
    customFilters,
    filterChips,
    searchPlaceholder,
    renderRowActions,
    gridContainerRef,
    gridHeight,
  }
}

/**
 * Page size assumed while the config is still loading, only to size the grid
 * container: it mirrors the limit every `TableDefinition::defaultPagination`
 * returns, so the skeleton block does not resize when the real config lands.
 */
const FALLBACK_PAGE_SIZE = 25
