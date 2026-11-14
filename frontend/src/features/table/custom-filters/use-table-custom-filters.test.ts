// @vitest-environment jsdom
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
    requestValues: {},
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

  // Ticket #428054 ("metto il filtro dei richiami di oggi, dopo poco si toglie da solo"):
  // AG Grid emits filterChanged AFTER setFilterModel returns, so the echo of
  // applyRules' own clear reached the grid handler outside activate()'s
  // suppression and switched the custom filter off again.
  it('keeps the custom filter active when the grid echoes its own clear asynchronously (#428054)', async () => {
    let model: Record<string, unknown> = { next_callback_at: { filterType: 'date', type: 'equals', dateFrom: '2026-11-13' } }
    const listeners: Array<() => void> = []
    const gridApi = {
      isDestroyed: () => false,
      getFilterModel: () => model,
      setFilterModel: (next: Record<string, unknown>) => {
        model = next
        setTimeout(() => listeners.forEach((listener) => listener()), 0)
      },
    } as unknown as GridApi<TableRow>

    const { result } = renderHook(() =>
      useSetup({
        gridApi,
        advancedFilters: stubAdvancedFilters(vi.fn()),
        refreshGrid: vi.fn(),
        onFilterModelApplied: vi.fn(),
      }),
    )
    listeners.push(() => result.current.handleGridFilterChanged())

    act(() => {
      result.current.applyRules(RULES, { name: 'Richiami di oggi' })
    })
    await act(() => new Promise((resolve) => setTimeout(resolve, 10)))

    expect(result.current.active.state).toEqual({ rules: RULES, viewId: undefined, name: 'Richiami di oggi' })
  })

  it('a column filter set while the custom filter is active switches the custom filter off (D-2)', () => {
    let model: Record<string, unknown> = {}
    const gridApi = {
      isDestroyed: () => false,
      getFilterModel: () => model,
      setFilterModel: (next: Record<string, unknown>) => {
        model = next
      },
    } as unknown as GridApi<TableRow>

    const { result } = renderHook(() =>
      useSetup({
        gridApi,
        advancedFilters: stubAdvancedFilters(vi.fn()),
        refreshGrid: vi.fn(),
        onFilterModelApplied: vi.fn(),
      }),
    )
    act(() => {
      result.current.applyRules(RULES, {})
    })

    model = { name: { filterType: 'text', type: 'contains', filter: 'rossi' } }
    act(() => result.current.handleGridFilterChanged())

    expect(result.current.active.state).toBeNull()
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
