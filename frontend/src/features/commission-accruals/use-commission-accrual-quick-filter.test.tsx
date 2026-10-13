import { describe, expect, it, vi } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import {
  buildAccrualQuickFilterPatch,
  readAccrualQuickFilter,
  useAccrualQuickFilter,
} from '@/features/commission-accruals/use-commission-accrual-quick-filter'
import type { TableViewHandle } from '@/features/table/table-view'

const OPEN_SET = { filterType: 'set', values: ['open'] }
const SETTLED_SET = { filterType: 'set', values: ['settled_externally'] }
const IN_STATEMENT_SET = { filterType: 'set', values: ['draft', 'issued'] }

describe('commission accrual quick filter (spec 0212 AC-021)', () => {
  it('defaults to Aperto: forces status=open only', () => {
    const { result } = renderHook(() => useAccrualQuickFilter({ current: null }))

    expect(result.current.forcedFilterModel).toEqual({ status: OPEN_SET })
    expect(result.current.quickFilter).toBe('open')
  })

  it.each([
    ['open', { status: OPEN_SET }],
    ['in_statement', { status: IN_STATEMENT_SET }],
    ['settled_externally', { status: SETTLED_SET }],
    ['all', { status: null }],
  ] as const)('writes the %s filter model patch through the table handle', (filter, patch) => {
    const setFilterModel = vi.fn()
    const tableRef = { current: { setFilterModel } as unknown as TableViewHandle }
    const { result } = renderHook(() => useAccrualQuickFilter(tableRef))

    act(() => result.current.setQuickFilter(filter))

    expect(setFilterModel).toHaveBeenCalledWith(patch)
    expect(buildAccrualQuickFilterPatch(filter)).toEqual(patch)
  })

  it('reads the active quick filter back from the live grid model', () => {
    expect(readAccrualQuickFilter({ status: OPEN_SET })).toBe('open')
    expect(readAccrualQuickFilter({ status: SETTLED_SET })).toBe('settled_externally')
    expect(readAccrualQuickFilter({ status: IN_STATEMENT_SET })).toBe('in_statement')
    expect(readAccrualQuickFilter({})).toBe('all')
    expect(readAccrualQuickFilter({ status: { filterType: 'set', values: ['open', 'settled_externally'] } })).toBeNull()
  })

  it('follows the grid filter model reported by the table', () => {
    const { result } = renderHook(() => useAccrualQuickFilter({ current: null }))

    act(() => result.current.onFilterModelChange({}))

    expect(result.current.quickFilter).toBe('all')
  })
})
