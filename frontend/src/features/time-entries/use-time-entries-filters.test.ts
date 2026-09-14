import { beforeEach, describe, expect, it } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import {
  createDefaultTimeEntriesFilters,
  TIME_ENTRIES_FILTERS_STORAGE_KEY,
} from '@/features/time-entries/time-entries-filters'
import { getPeriodRange, shiftAnchor, toDateString } from '@/features/time-entries/time-entry-period'
import { useTimeEntriesFilters } from '@/features/time-entries/use-time-entries-filters'

/** Spec 0122 AC-032/AC-033: filters + period navigation, persisted to localStorage. */

beforeEach(() => {
  window.localStorage.clear()
})

function readStorage() {
  const raw = window.localStorage.getItem(TIME_ENTRIES_FILTERS_STORAGE_KEY)
  return raw ? JSON.parse(raw) : null
}

describe('useTimeEntriesFilters — hydration', () => {
  it('hydrates the current-week default when nothing is stored', () => {
    const { result } = renderHook(() => useTimeEntriesFilters())

    expect(result.current.filters).toEqual(createDefaultTimeEntriesFilters())
    expect(result.current.navigationUnit).toBe('week')
    expect(result.current.anchorDate).toBe(toDateString(new Date()))
  })
})

describe('useTimeEntriesFilters — filters state', () => {
  it('persists a filter change to localStorage', () => {
    const { result } = renderHook(() => useTimeEntriesFilters())

    act(() => {
      result.current.setFilters((current) => ({
        ...current,
        values: { ...current.values, user_id: '5' },
      }))
    })

    expect(result.current.filters.values.user_id).toBe('5')
    expect(readStorage().values.user_id).toBe('5')
  })

  it('removes exactly the given filter chip', () => {
    const { result } = renderHook(() => useTimeEntriesFilters())

    act(() => {
      result.current.setFilters((current) => ({
        ...current,
        values: { ...current.values, user_id: '5', task_type_ids: ['1'] },
      }))
    })
    act(() => result.current.removeFilterChip('user_id'))

    expect(result.current.filters.values.user_id).toBeUndefined()
    expect(result.current.filters.values.task_type_ids).toEqual(['1'])
  })

  it('updates sortBy/sortDirection', () => {
    const { result } = renderHook(() => useTimeEntriesFilters())

    act(() => result.current.setSort('target_minutes', 'desc'))

    expect(result.current.filters.sortBy).toBe('target_minutes')
    expect(result.current.filters.sortDirection).toBe('desc')
  })

  it('restores the defaults on reset, including the week navigation unit', () => {
    const { result } = renderHook(() => useTimeEntriesFilters())

    act(() => {
      result.current.setFilters((current) => ({ ...current, values: { ...current.values, user_id: '5' } }))
      result.current.setCustomDateRange('2026-01-01', '2026-01-31')
    })
    act(() => result.current.resetFilters())

    expect(result.current.filters).toEqual(createDefaultTimeEntriesFilters())
    expect(result.current.navigationUnit).toBe('week')
  })
})

describe('useTimeEntriesFilters — period navigation', () => {
  it('selects a preset, clearing any explicit date range', () => {
    const { result } = renderHook(() => useTimeEntriesFilters())

    act(() => result.current.selectPeriodPreset('month'))

    expect(result.current.navigationUnit).toBe('month')
    expect(result.current.filters.values.period_preset).toEqual(['month'])
    expect(result.current.filters.values.date_from).toBe('')
    expect(result.current.filters.values.date_to).toBe('')
  })

  it('moves to the previous/next week with explicit dates, clearing period_preset', () => {
    const { result } = renderHook(() => useTimeEntriesFilters())

    act(() => result.current.goToPreviousPeriod())

    const expectedPrevious = getPeriodRange('week', shiftAnchor('week', new Date(), -1))
    expect(result.current.filters.values.period_preset).toEqual([])
    expect(result.current.filters.values.date_from).toBe(expectedPrevious.from)
    expect(result.current.filters.values.date_to).toBe(expectedPrevious.to)
    expect(result.current.anchorDate).toBe(expectedPrevious.from)

    act(() => result.current.goToNextPeriod())

    const expectedCurrent = getPeriodRange('week', new Date())
    expect(result.current.filters.values.date_from).toBe(expectedCurrent.from)
    expect(result.current.filters.values.date_to).toBe(expectedCurrent.to)
  })

  it('returns to the relative preset on "today", clearing explicit dates', () => {
    const { result } = renderHook(() => useTimeEntriesFilters())

    act(() => result.current.goToPreviousPeriod())
    act(() => result.current.goToToday())

    expect(result.current.filters.values.period_preset).toEqual(['week'])
    expect(result.current.filters.values.date_from).toBe('')
    expect(result.current.filters.values.date_to).toBe('')
    expect(result.current.anchorDate).toBe(toDateString(new Date()))
  })

  it('sets a custom range, clearing the navigation unit so prev/next/today no-op', () => {
    const { result } = renderHook(() => useTimeEntriesFilters())

    act(() => result.current.setCustomDateRange('2026-01-01', '2026-01-31'))

    expect(result.current.navigationUnit).toBeNull()
    expect(result.current.filters.values.period_preset).toEqual([])
    expect(result.current.filters.values.date_from).toBe('2026-01-01')
    expect(result.current.filters.values.date_to).toBe('2026-01-31')

    act(() => result.current.goToNextPeriod())

    expect(result.current.filters.values.date_from).toBe('2026-01-01')
    expect(result.current.filters.values.date_to).toBe('2026-01-31')
  })
})
