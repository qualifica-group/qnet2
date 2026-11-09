// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vitest'
import { renderHook } from '@testing-library/react'
import { useTableAdvancedFilters } from '@/features/table/advanced-filters/use-table-advanced-filters'
import type { AdvancedFilterDescriptor } from '@/features/table/advanced-filters/types'

vi.mock('@/features/table/use-table-filters', () => ({
  useSaveTableFilters: () => ({ mutate: vi.fn(), isPending: false }),
}))

function descriptor(name: string): AdvancedFilterDescriptor {
  return { name, label: name, type: 'enum', order: 0, required: true, visible: true, width: 'md', multiple: false, defaultValue: 'mine' }
}

const DESCRIPTORS = [descriptor('assignment'), descriptor('registry')]
const HIDDEN = ['assignment']

describe('useTableAdvancedFilters', () => {
  it('offers every filter of the domain by default', () => {
    const { result } = renderHook(() =>
      useTableAdvancedFilters({ domain: 'work-orders', descriptors: DESCRIPTORS, applied: null, onApplied: vi.fn() }),
    )

    expect(result.current.descriptors.map(({ name }) => name)).toEqual(['assignment', 'registry'])
  })

  it('drops the hidden filters, so a persisted value of theirs is never sent', () => {
    const { result } = renderHook(() =>
      useTableAdvancedFilters({
        domain: 'work-orders',
        descriptors: DESCRIPTORS,
        applied: { assignment: 'all' },
        onApplied: vi.fn(),
        hidden: HIDDEN,
      }),
    )

    expect(result.current.descriptors.map(({ name }) => name)).toEqual(['registry'])
    expect(result.current.filters.getApplied()).toEqual({})
  })
})
