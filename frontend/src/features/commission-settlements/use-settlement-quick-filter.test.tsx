import { describe, expect, it, vi } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import {
  buildSettlementQuickFilterPatch,
  readSettlementQuickFilter,
  useSettlementQuickFilter,
} from '@/features/commission-settlements/use-settlement-quick-filter'
import type { TableViewHandle } from '@/features/table/table-view'

const set = (value: string) => ({ filterType: 'set', values: [value] })

describe('settlement quick filter (spec 0213 AC-021)', () => {
  it('defaults to Bozze: forces status=DRAFT', () => {
    const { result } = renderHook(() => useSettlementQuickFilter({ current: null }))

    expect(result.current.forcedFilterModel).toEqual({ status: set('DRAFT') })
    expect(result.current.quickFilter).toBe('draft')
  })

  it.each([
    ['draft', { status: set('DRAFT') }],
    ['issued', { status: set('ISSUED') }],
    ['document_received', { status: set('DOCUMENT_RECEIVED') }],
    ['approved', { status: set('APPROVED') }],
    ['cancelled', { status: set('CANCELLED') }],
    ['all', { status: null }],
  ] as const)('writes the %s patch through the table handle', (filter, patch) => {
    const setFilterModel = vi.fn()
    const tableRef = { current: { setFilterModel } as unknown as TableViewHandle }
    const { result } = renderHook(() => useSettlementQuickFilter(tableRef))

    act(() => result.current.setQuickFilter(filter))

    expect(setFilterModel).toHaveBeenCalledWith(patch)
    expect(buildSettlementQuickFilterPatch(filter)).toEqual(patch)
  })

  it('reads the active quick filter back from the grid model', () => {
    expect(readSettlementQuickFilter({ status: set('ISSUED') })).toBe('issued')
    expect(readSettlementQuickFilter({ status: set('DOCUMENT_RECEIVED') })).toBe('document_received')
    expect(readSettlementQuickFilter({})).toBe('all')
    expect(readSettlementQuickFilter({ status: { filterType: 'set', values: ['DRAFT', 'ISSUED'] } })).toBeNull()
  })
})
