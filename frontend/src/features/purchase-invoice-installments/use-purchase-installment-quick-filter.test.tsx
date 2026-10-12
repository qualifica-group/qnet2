import { describe, expect, it, vi } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import {
  buildPurchaseQuickFilterPatch,
  readPurchaseQuickFilter,
  usePurchaseInstallmentQuickFilter,
} from '@/features/purchase-invoice-installments/use-purchase-installment-quick-filter'
import type { TableViewHandle } from '@/features/table/table-view'

const UNPAID_SET = { filterType: 'set', values: ['unpaid'] }
const PAID_SET = { filterType: 'set', values: ['paid'] }
const OVERDUE_YES = { filterType: 'set', values: ['yes'] }
const OVERDUE_NO = { filterType: 'set', values: ['no'] }

describe('purchase installment quick filter (spec 0211 AC-014)', () => {
  it('defaults to Da pagare: forces the unpaid status only', () => {
    const tableRef = { current: null }
    const { result } = renderHook(() => usePurchaseInstallmentQuickFilter(tableRef))

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
    const { result } = renderHook(() => usePurchaseInstallmentQuickFilter(tableRef))

    act(() => result.current.setQuickFilter(filter))

    expect(setFilterModel).toHaveBeenCalledWith(patch)
    expect(buildPurchaseQuickFilterPatch(filter)).toEqual(patch)
  })

  it('reads the active quick filter back from the live grid model', () => {
    expect(readPurchaseQuickFilter({ status: UNPAID_SET })).toBe('unpaid')
    expect(readPurchaseQuickFilter({ status: UNPAID_SET, overdue: OVERDUE_NO })).toBe('due')
    expect(readPurchaseQuickFilter({ status: UNPAID_SET, overdue: OVERDUE_YES })).toBe('overdue')
    expect(readPurchaseQuickFilter({ status: PAID_SET })).toBe('paid')
    expect(readPurchaseQuickFilter({})).toBe('all')
    expect(readPurchaseQuickFilter({ status: PAID_SET, overdue: OVERDUE_YES })).toBeNull()
    expect(readPurchaseQuickFilter({ overdue: OVERDUE_YES })).toBeNull()
  })

  it('follows the grid filter model reported by the table', () => {
    const tableRef = { current: null }
    const { result } = renderHook(() => usePurchaseInstallmentQuickFilter(tableRef))

    act(() => result.current.onFilterModelChange({}))

    expect(result.current.quickFilter).toBe('all')
  })
})
