import { renderHook } from '@testing-library/react'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { useActiveFilterChips } from '@/features/table/custom-filters/use-active-filter-chips'
import type { AdvancedFilterDescriptor } from '@/features/table/advanced-filters/types'
import type { TableColumn } from '@/features/table/types'

const COLUMNS: TableColumn[] = [
  { id: 'email', label: 'users.columns.email', type: 'text', visible: true, width: null, order: 0, sortable: true, filterable: true, filterType: 'text' },
  {
    id: 'status',
    label: 'users.columns.status',
    type: 'badge',
    visible: true,
    width: null,
    order: 1,
    sortable: true,
    filterable: true,
    filterType: 'set',
    badges: [{ value: 'active', label: 'Active', color: null, icon: null }],
  },
]

const DESCRIPTOR: AdvancedFilterDescriptor = {
  name: 'priority',
  label: 'tasks.advancedFilters.priority',
  type: 'text',
  order: 0,
  required: false,
  visible: true,
  width: 'md',
  multiple: false,
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('useActiveFilterChips', () => {
  it('builds one chip per column filter with "Column: value"', () => {
    const onRemoveColumnFilter = vi.fn()
    const { result } = renderHook(() =>
      useActiveFilterChips({
        columns: COLUMNS,
        filterModel: { email: { filterType: 'text', type: 'contains', filter: 'acme' } },
        onRemoveColumnFilter,
        advancedDescriptors: [],
        advancedFilters: { activeValues: {}, clearField: vi.fn() },
        search: '',
        onClearSearch: vi.fn(),
        customFilter: null,
        onRemoveCustomFilter: vi.fn(),
        onClearAll: vi.fn(),
      }),
    )

    expect(result.current.chips).toHaveLength(1)
    expect(result.current.chips[0].label).toBe('Email: acme')

    result.current.chips[0].onRemove()
    expect(onRemoveColumnFilter).toHaveBeenCalledWith('email')
  })

  it('truncates a set filter to 3 values + N', () => {
    const { result } = renderHook(() =>
      useActiveFilterChips({
        columns: COLUMNS,
        filterModel: { status: { values: ['a', 'b', 'c', 'd', 'e'] } },
        onRemoveColumnFilter: vi.fn(),
        advancedDescriptors: [],
        advancedFilters: { activeValues: {}, clearField: vi.fn() },
        search: '',
        onClearSearch: vi.fn(),
        customFilter: null,
        onRemoveCustomFilter: vi.fn(),
        onClearAll: vi.fn(),
      }),
    )

    expect(result.current.chips[0].label).toBe('users.columns.status: a, b, c +2')
  })

  it('builds an advanced filter chip and its remove calls clearField', () => {
    const clearField = vi.fn()
    const { result } = renderHook(() =>
      useActiveFilterChips({
        columns: [],
        filterModel: {},
        onRemoveColumnFilter: vi.fn(),
        advancedDescriptors: [DESCRIPTOR],
        advancedFilters: { activeValues: { priority: 'high' }, clearField },
        search: '',
        onClearSearch: vi.fn(),
        customFilter: null,
        onRemoveCustomFilter: vi.fn(),
        onClearAll: vi.fn(),
      }),
    )

    expect(result.current.chips).toHaveLength(1)
    result.current.chips[0].onRemove()
    expect(clearField).toHaveBeenCalledWith('priority')
  })

  it('builds a search chip only when the term is non-blank', () => {
    const { result, rerender } = renderHook(
      (props: { search: string }) =>
        useActiveFilterChips({
          columns: [],
          filterModel: {},
          onRemoveColumnFilter: vi.fn(),
          advancedDescriptors: [],
          advancedFilters: { activeValues: {}, clearField: vi.fn() },
          search: props.search,
          onClearSearch: vi.fn(),
          customFilter: null,
          onRemoveCustomFilter: vi.fn(),
          onClearAll: vi.fn(),
        }),
      { initialProps: { search: '' } },
    )

    expect(result.current.chips).toHaveLength(0)

    rerender({ search: 'acme' })
    expect(result.current.chips.some((chip) => chip.id === 'search')).toBe(true)
  })

  it('builds a custom filter chip with the view name, or a fallback label', () => {
    const { result } = renderHook(() =>
      useActiveFilterChips({
        columns: [],
        filterModel: {},
        onRemoveColumnFilter: vi.fn(),
        advancedDescriptors: [],
        advancedFilters: { activeValues: {}, clearField: vi.fn() },
        search: '',
        onClearSearch: vi.fn(),
        customFilter: { rules: { and: [], or: [] }, name: 'My filter' },
        onRemoveCustomFilter: vi.fn(),
        onClearAll: vi.fn(),
      }),
    )

    expect(result.current.chips[0].label).toBe('My filter')
  })
})
