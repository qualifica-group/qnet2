import { describe, expect, it, vi } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import {
  buildQuickFilterPatch,
  readQuickFilter,
  useInstallmentQuickFilter,
} from '@/features/invoice-installments/use-installment-quick-filter'
import type { TableViewHandle } from '@/features/table/table-view'

const OPEN_SET = { filterType: 'set', values: ['unpaid', 'partially_paid'] }

describe('installment quick filter (spec 0197 AC-018)', () => {
  it('defaults to Aperte: forces the unpaid and partially paid status set only', () => {
    const tableRef = { current: null }
    const { result } = renderHook(() => useInstallmentQuickFilter(tableRef))

    expect(result.current.forcedFilterModel).toEqual({ status: OPEN_SET })
    expect(result.current.quickFilter).toBe('open')
  })

  it.each([
    ['open', { status: OPEN_SET, overdue: null }],
    ['overdue', { status: OPEN_SET, overdue: { filterType: 'set', values: ['yes'] } }],
    ['all', { status: null, overdue: null }],
  ] as const)('writes the %s filter model patch through the table handle', (filter, patch) => {
    const setFilterModel = vi.fn()
    const tableRef = { current: { setFilterModel } as unknown as TableViewHandle }
    const { result } = renderHook(() => useInstallmentQuickFilter(tableRef))

    act(() => result.current.setQuickFilter(filter))

    expect(setFilterModel).toHaveBeenCalledWith(patch)
    expect(buildQuickFilterPatch(filter)).toEqual(patch)
  })

  it('reads the active quick filter back from the live grid model', () => {
    expect(readQuickFilter({ status: OPEN_SET })).toBe('open')
    expect(readQuickFilter({ status: OPEN_SET, overdue: { filterType: 'set', values: ['yes'] } })).toBe('overdue')
    expect(readQuickFilter({})).toBe('all')
    expect(readQuickFilter({ status: { filterType: 'set', values: ['paid'] } })).toBeNull()
  })

  it('follows the grid filter model reported by the table', () => {
    const tableRef = { current: null }
    const { result } = renderHook(() => useInstallmentQuickFilter(tableRef))

    act(() => result.current.onFilterModelChange({}))

    expect(result.current.quickFilter).toBe('all')
  })
})
