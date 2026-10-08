import { describe, expect, it, vi } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import {
  buildQuickFilterPatch,
  readQuickFilter,
  useInstallmentQuickFilter,
} from '@/features/invoice-installments/use-installment-quick-filter'
import type { TableViewHandle } from '@/features/table/table-view'

const UNPAID_SET = { filterType: 'set', values: ['unpaid'] }
const PAID_SET = { filterType: 'set', values: ['paid'] }
const OVERDUE_YES = { filterType: 'set', values: ['yes'] }
const OVERDUE_NO = { filterType: 'set', values: ['no'] }

describe('installment quick filter (spec 0197 AC-018, D-9)', () => {
  it('defaults to Da incassare: forces the unpaid status only', () => {
    const tableRef = { current: null }
    const { result } = renderHook(() => useInstallmentQuickFilter(tableRef))

    expect(result.current.forcedFilterModel).toEqual({ status: UNPAID_SET })
    expect(result.current.quickFilter).toBe('unpaid')
  })

  it.each([
    ['unpaid', { status: UNPAID_SET, overdue: null }],
    ['due', { status: UNPAID_SET, overdue: OVERDUE_NO }],
    ['overdue', { status: UNPAID_SET, overdue: OVERDUE_YES }],
    ['paid', { status: PAID_SET, overdue: null }],
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
    expect(readQuickFilter({ status: UNPAID_SET })).toBe('unpaid')
    expect(readQuickFilter({ status: UNPAID_SET, overdue: OVERDUE_NO })).toBe('due')
    expect(readQuickFilter({ status: UNPAID_SET, overdue: OVERDUE_YES })).toBe('overdue')
    expect(readQuickFilter({ status: PAID_SET })).toBe('paid')
    expect(readQuickFilter({})).toBe('all')
    expect(readQuickFilter({ status: PAID_SET, overdue: OVERDUE_YES })).toBeNull()
    expect(readQuickFilter({ overdue: OVERDUE_YES })).toBeNull()
  })

  it('follows the grid filter model reported by the table', () => {
    const tableRef = { current: null }
    const { result } = renderHook(() => useInstallmentQuickFilter(tableRef))

    act(() => result.current.onFilterModelChange({}))

    expect(result.current.quickFilter).toBe('all')
  })
})
