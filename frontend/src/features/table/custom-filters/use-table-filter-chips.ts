import { useCallback } from 'react'
import type { GridApi } from 'ag-grid-community'
import { useActiveFilterChips, type UseActiveFilterChipsResult } from '@/features/table/custom-filters/use-active-filter-chips'
import type { UseTableCustomFiltersResult } from '@/features/table/custom-filters/use-table-custom-filters'
import type { UseAdvancedFiltersResult } from '@/features/table/advanced-filters/use-advanced-filters'
import type { AdvancedFilterDescriptor } from '@/features/table/advanced-filters/types'
import type { TableColumn, TableRow } from '@/features/table/types'

interface UseTableFilterChipsArgs {
  gridApi: GridApi<TableRow> | null
  columns: TableColumn[]
  filterModel: Record<string, unknown>
  advancedDescriptors: AdvancedFilterDescriptor[]
  advancedFilters: UseAdvancedFiltersResult
  search: string
  onClearSearch: () => void
  customFilters: UseTableCustomFiltersResult
  refreshGrid: () => void
  /** `useTableLayoutPersistence`'s own reset (also persists + remounts the grid). */
  resetColumnFilters: () => Promise<void>
}

/**
 * Composes `useActiveFilterChips` with the concrete remove/clear-all handlers
 * a live `TableView` needs (spec 0158 D-5): removing one column filter edits
 * the grid's filterModel directly, "Azzera tutto" clears every filter kind at
 * once. Extracted so `table-view.tsx` only wires one more hook
 * (engineering.md §6).
 */
export function useTableFilterChips({
  gridApi,
  columns,
  filterModel,
  advancedDescriptors,
  advancedFilters,
  search,
  onClearSearch,
  customFilters,
  refreshGrid,
  resetColumnFilters,
}: UseTableFilterChipsArgs): UseActiveFilterChipsResult {
  const onRemoveColumnFilter = useCallback(
    (columnId: string) => {
      if (!gridApi) {
        return
      }
      const next = { ...(gridApi.getFilterModel() ?? {}) }
      delete next[columnId]
      gridApi.setFilterModel(next)
    },
    [gridApi],
  )

  const onRemoveCustomFilter = useCallback(() => {
    customFilters.active.deactivate()
    refreshGrid()
  }, [customFilters.active, refreshGrid])

  const onClearAll = useCallback(() => {
    onClearSearch()
    advancedFilters.reset()
    customFilters.active.deactivate()
    void resetColumnFilters()
  }, [onClearSearch, advancedFilters, customFilters.active, resetColumnFilters])

  return useActiveFilterChips({
    columns,
    filterModel,
    onRemoveColumnFilter,
    advancedDescriptors,
    advancedFilters,
    search,
    onClearSearch,
    customFilter: customFilters.active.state,
    onRemoveCustomFilter,
    onClearAll,
  })
}
