import { useCallback, useState } from 'react'
import type { GridApi } from 'ag-grid-community'
import type { UseCustomFilterStateResult } from '@/features/table/custom-filters/use-custom-filter-state'
import type { UseAdvancedFiltersResult } from '@/features/table/advanced-filters/use-advanced-filters'
import type { FilterRules, TableFilterView, TableRow } from '@/features/table/types'

interface UseTableCustomFiltersArgs {
  /**
   * Built by the caller via `useCustomFilterState()` BEFORE `advancedFilters`
   * (its `notifyExternalChange` feeds `useTableAdvancedFilters`'s `onApplied`,
   * so the ordering avoids a callback cycle between the two hooks).
   */
  active: UseCustomFilterStateResult
  gridApi: GridApi<TableRow> | null
  advancedFilters: UseAdvancedFiltersResult
  refreshGrid: () => void
  /** Mirrors `useTableLayoutPersistence`'s `setFiltersCustomizedLocally`. */
  onFilterModelApplied: (hasFilters: boolean) => void
}

export interface UseTableCustomFiltersResult {
  /** The active custom filter's rules (or none), and the lazy getter the datasource reads. */
  active: UseCustomFilterStateResult
  /** Whether the rule builder dialog is open. */
  builderOpen: boolean
  /** The view being edited, or `null` for a brand-new custom filter. */
  editingView: TableFilterView | null
  openNewFilter: () => void
  openEditFilter: (view: TableFilterView) => void
  closeBuilder: () => void
  /**
   * Activates `rules` as the domain's custom filter (spec 0158 D-2): clears
   * the grid's column filterModel and resets the advanced filters to their
   * default, then makes `rules` the one the datasource sends.
   */
  applyRules: (rules: FilterRules, meta: { viewId?: number; name?: string }) => void
  /**
   * The grid's `filterChanged`: a column filter the user sets wins over the
   * active custom filter (spec 0158 D-2, "vince l'ultimo").
   */
  handleGridFilterChanged: () => void
}

/**
 * Composition hook bundling the custom filter's in-memory state (spec 0158)
 * with the rule builder dialog's open/editing state, so `TableView` only
 * holds one value instead of several `useState`s (engineering.md §6). Column/
 * advanced filter changes deactivating the custom filter are wired by the
 * caller through `active.notifyExternalChange` (it needs the grid's own
 * `onFilterChanged` and the advanced filters' `onApplied`, both owned by
 * other hooks `TableView` already composes).
 */
export function useTableCustomFilters({
  active,
  gridApi,
  advancedFilters,
  refreshGrid,
  onFilterModelApplied,
}: UseTableCustomFiltersArgs): UseTableCustomFiltersResult {
  const [builderOpen, setBuilderOpen] = useState(false)
  const [editingView, setEditingView] = useState<TableFilterView | null>(null)

  const openNewFilter = useCallback(() => {
    setEditingView(null)
    setBuilderOpen(true)
  }, [])

  const openEditFilter = useCallback((view: TableFilterView) => {
    setEditingView(view)
    setBuilderOpen(true)
  }, [])

  const closeBuilder = useCallback(() => setBuilderOpen(false), [])

  const applyRules = useCallback(
    (rules: FilterRules, meta: { viewId?: number; name?: string }) => {
      active.activate(rules, meta, () => {
        gridApi?.setFilterModel({})
        onFilterModelApplied(false)
        advancedFilters.reset()
      })
      refreshGrid()
    },
    [active, gridApi, onFilterModelApplied, advancedFilters, refreshGrid],
  )

  // AG Grid emits filterChanged only AFTER setFilterModel has returned, so the
  // echo of applyRules' own clear lands outside activate()'s suppression
  // (ticket #428054: the custom filter switched itself off moments after
  // being applied). A custom filter is active only over an empty column
  // filterModel, so a change that leaves one in place is the user's own.
  const { notifyExternalChange } = active
  const handleGridFilterChanged = useCallback(() => {
    if (gridApi && !gridApi.isDestroyed() && Object.keys(gridApi.getFilterModel()).length > 0) {
      notifyExternalChange()
    }
  }, [gridApi, notifyExternalChange])

  return { active, builderOpen, editingView, openNewFilter, openEditFilter, closeBuilder, applyRules, handleGridFilterChanged }
}
