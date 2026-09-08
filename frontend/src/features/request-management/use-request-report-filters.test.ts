import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import {
  reconcileCategoryKeys,
  useRequestReportFilters,
} from '@/features/request-management/use-request-report-filters'
import type { RequestReportFormValues } from '@/features/request-management/request-report-schema'
import type { RequestReportCategory } from '@/features/request-management/report-api'

/**
 * User directive 2026-09-08: the filters applied to the dashboard survive a
 * reload. Same persistence shape as `useRequestManagementCategoryPreference`;
 * reconciling the stored branches against the ones the report still offers is
 * the caller's job and is covered here as a pure function.
 */

const STORAGE_KEY = 'request-management.report-filters'

const CATEGORIES: RequestReportCategory[] = [
  { key: 'gol', label: 'GOL' },
  { key: 'consulenza', label: 'Consulenza' },
]

function filters(overrides: Partial<RequestReportFormValues> = {}): RequestReportFormValues {
  return {
    date_from: '2026-09-01',
    date_to: '2026-09-30',
    category_keys: ['gol'],
    row_mode: 'total_only',
    operator_keys: [],
    ...overrides,
  }
}

beforeEach(() => {
  window.localStorage.clear()
})

afterEach(() => {
  window.localStorage.clear()
})

describe('useRequestReportFilters', () => {
  it('falls back to the current-week defaults when nothing was stored yet', () => {
    const { result } = renderHook(() => useRequestReportFilters())

    expect(result.current.filters.date_from).not.toBe('')
    expect(result.current.filters.date_to >= result.current.filters.date_from).toBe(true)
    expect(result.current.filters.category_keys).toEqual([])
    expect(result.current.filters.row_mode).toBe('all')
  })

  it('persists the applied filters to localStorage', () => {
    const { result } = renderHook(() => useRequestReportFilters())

    act(() => result.current.setFilters(filters()))

    expect(result.current.filters).toEqual(filters())
    expect(JSON.parse(window.localStorage.getItem(STORAGE_KEY) as string)).toEqual(filters())
  })

  it('survives a remount: a fresh mount reads the previously applied filters back', () => {
    const first = renderHook(() => useRequestReportFilters())
    act(() => first.result.current.setFilters(filters()))
    first.unmount()

    const second = renderHook(() => useRequestReportFilters())

    expect(second.result.current.filters).toEqual(filters())
  })

  it('ignores a stored payload of the wrong shape instead of feeding it to the query', () => {
    window.localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({ date_from: '2026-09-01', date_to: '2026-09-30', category_keys: [7], row_mode: 'nope' }),
    )

    const { result } = renderHook(() => useRequestReportFilters())

    expect(result.current.filters.category_keys).toEqual([])
    expect(result.current.filters.row_mode).toBe('all')
  })

  it('ignores unparsable storage content', () => {
    window.localStorage.setItem(STORAGE_KEY, 'not json')

    const { result } = renderHook(() => useRequestReportFilters())

    expect(result.current.filters.row_mode).toBe('all')
  })

  it('keeps only the four contract fields, dropping anything an older shape added', () => {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify({ ...filters(), legacy_field: 'x' }))

    const { result } = renderHook(() => useRequestReportFilters())

    expect(result.current.filters).toEqual(filters())
  })
})

describe('reconcileCategoryKeys', () => {
  it('returns the same object when every stored branch still exists', () => {
    const current = filters({ category_keys: ['gol', 'consulenza'] })

    expect(reconcileCategoryKeys(current, CATEGORIES)).toBe(current)
  })

  it('drops a branch the report no longer offers', () => {
    const current = filters({ category_keys: ['gol', 'retired'] })

    expect(reconcileCategoryKeys(current, CATEGORIES).category_keys).toEqual(['gol'])
  })

  it('falls back to every branch when nothing stored survives (AC-050)', () => {
    const current = filters({ category_keys: ['retired'] })

    expect(reconcileCategoryKeys(current, CATEGORIES).category_keys).toEqual(['gol', 'consulenza'])
  })

  it('seeds every branch on a first, empty selection (AC-050)', () => {
    const current = filters({ category_keys: [] })

    expect(reconcileCategoryKeys(current, CATEGORIES).category_keys).toEqual(['gol', 'consulenza'])
  })
})
