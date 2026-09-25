import { act, renderHook } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { useTableCustomFilters } from '@/features/table/custom-filters/use-table-custom-filters'
import { useCustomFilterState } from '@/features/table/custom-filters/use-custom-filter-state'
import type { GridApi } from 'ag-grid-community'
import type { UseAdvancedFiltersResult } from '@/features/table/advanced-filters/use-advanced-filters'
import type { FilterRules, TableFilterView, TableRow } from '@/features/table/types'

const RULES: FilterRules = { and: [{ field: 'status', operator: 'equals', value: 'open' }], or: [] }

function stubAdvancedFilters(reset: () => void): UseAdvancedFiltersResult {
  return {
    draft: {},
    setFieldValue: vi.fn(),
    isFieldDisabled: () => false,
    isFieldInvalid: () => false,
    dependencyParamsFor: () => undefined,
    canApply: true,
    activeValues: {},
    activeCount: 0,
    apply: vi.fn(),
    reset,
    clearField: vi.fn(),
    applyValues: vi.fn(),
    isSaving: false,
    getApplied: () => ({}),
  }
}

/** Mirrors `TableView`'s composition: `useCustomFilterState()` built first, then wired into `useTableCustomFilters`. */
function useSetup(args: {
  gridApi: GridApi<TableRow> | null
  advancedFilters: UseAdvancedFiltersResult
  refreshGrid: () => void
  onFilterModelApplied: (hasFilters: boolean) => void
}) {
  const active = useCustomFilterState()
  const customFilters = useTableCustomFilters({ active, ...args })
  return customFilters
}

describe('useTableCustomFilters', () => {
  it('applyRules clears the grid filterModel, resets advanced filters, activates and refreshes', () => {
    const setFilterModel = vi.fn()
    const gridApi = { setFilterModel } as unknown as GridApi<TableRow>
    const resetAdvanced = vi.fn()
    const refreshGrid = vi.fn()
    const onFilterModelApplied = vi.fn()

    const { result } = renderHook(() =>
      useSetup({
        gridApi,
        advancedFilters: stubAdvancedFilters(resetAdvanced),
        refreshGrid,
        onFilterModelApplied,
      }),
    )

    act(() => {
      result.current.applyRules(RULES, { viewId: 3, name: 'Aperti' })
    })

    expect(setFilterModel).toHaveBeenCalledWith({})
    expect(onFilterModelApplied).toHaveBeenCalledWith(false)
    expect(resetAdvanced).toHaveBeenCalledTimes(1)
    expect(refreshGrid).toHaveBeenCalled()
    expect(result.current.active.state).toEqual({ rules: RULES, viewId: 3, name: 'Aperti' })
  })

  it('openNewFilter/openEditFilter/closeBuilder manage the dialog state', () => {
    const { result } = renderHook(() =>
      useSetup({
        gridApi: null,
        advancedFilters: stubAdvancedFilters(vi.fn()),
        refreshGrid: vi.fn(),
        onFilterModelApplied: vi.fn(),
      }),
    )

    expect(result.current.builderOpen).toBe(false)

    act(() => result.current.openNewFilter())
    expect(result.current.builderOpen).toBe(true)
    expect(result.current.editingView).toBeNull()

    act(() => result.current.closeBuilder())
    expect(result.current.builderOpen).toBe(false)

    const view: TableFilterView = {
      id: 1,
      name: 'v',
      filters: {},
      advanced_filters: {},
      visibility: 'private',
      owned: true,
      owner_name: null,
      rules: RULES,
      is_favorite: false,
    }
    act(() => result.current.openEditFilter(view))
    expect(result.current.builderOpen).toBe(true)
    expect(result.current.editingView).toEqual(view)
  })
})
