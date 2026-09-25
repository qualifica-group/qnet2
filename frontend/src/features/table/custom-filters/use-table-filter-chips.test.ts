import { renderHook } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { useTableFilterChips } from '@/features/table/custom-filters/use-table-filter-chips'
import type { GridApi } from 'ag-grid-community'
import type { UseTableCustomFiltersResult } from '@/features/table/custom-filters/use-table-custom-filters'
import type { UseAdvancedFiltersResult } from '@/features/table/advanced-filters/use-advanced-filters'
import type { TableRow } from '@/features/table/types'

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

function stubCustomFilters(deactivate: () => void): UseTableCustomFiltersResult {
  return {
    active: {
      state: null,
      activate: vi.fn(),
      deactivate,
      getActive: () => null,
      notifyExternalChange: vi.fn(),
    },
    builderOpen: false,
    editingView: null,
    openNewFilter: vi.fn(),
    openEditFilter: vi.fn(),
    closeBuilder: vi.fn(),
    applyRules: vi.fn(),
  }
}

describe('useTableFilterChips', () => {
  it('removing one column filter edits the grid filterModel without touching the others', () => {
    const setFilterModel = vi.fn()
    const gridApi = {
      getFilterModel: () => ({ email: { filter: 'a' }, status: { values: ['x'] } }),
      setFilterModel,
    } as unknown as GridApi<TableRow>

    const { result } = renderHook(() =>
      useTableFilterChips({
        gridApi,
        columns: [],
        filterModel: { email: { filter: 'a' }, status: { values: ['x'] } },
        advancedDescriptors: [],
        advancedFilters: stubAdvancedFilters(vi.fn()),
        search: '',
        onClearSearch: vi.fn(),
        customFilters: stubCustomFilters(vi.fn()),
        refreshGrid: vi.fn(),
        resetColumnFilters: vi.fn(),
      }),
    )

    result.current.chips.find((chip) => chip.id === 'column:email')?.onRemove()

    expect(setFilterModel).toHaveBeenCalledWith({ status: { values: ['x'] } })
  })

  it('onClearAll clears search, advanced filters, the custom filter and column filters', () => {
    const onClearSearch = vi.fn()
    const resetAdvanced = vi.fn()
    const deactivate = vi.fn()
    const resetColumnFilters = vi.fn().mockResolvedValue(undefined)

    const { result } = renderHook(() =>
      useTableFilterChips({
        gridApi: null,
        columns: [],
        filterModel: {},
        advancedDescriptors: [],
        advancedFilters: stubAdvancedFilters(resetAdvanced),
        search: 'acme',
        onClearSearch,
        customFilters: stubCustomFilters(deactivate),
        refreshGrid: vi.fn(),
        resetColumnFilters,
      }),
    )

    result.current.onClearAll()

    expect(onClearSearch).toHaveBeenCalledTimes(1)
    expect(resetAdvanced).toHaveBeenCalledTimes(1)
    expect(deactivate).toHaveBeenCalledTimes(1)
    expect(resetColumnFilters).toHaveBeenCalledTimes(1)
  })
})
