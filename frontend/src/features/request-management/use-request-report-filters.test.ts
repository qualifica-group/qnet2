import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import {
  reconcileCategoryKeys,
  reconcileSiteKeys,
  useRequestReportFilters,
} from '@/features/request-management/use-request-report-filters'
import type { RequestReportFormValues } from '@/features/request-management/request-report-schema'
import type { RequestReportCategory, RequestReportSite } from '@/features/request-management/report-api'

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

const SITES: RequestReportSite[] = [
  { key: '3', label: 'Via Roma 1 - Frattamaggiore' },
  { key: '7', label: 'Corso Italia 9 - Napoli' },
]

function filters(overrides: Partial<RequestReportFormValues> = {}): RequestReportFormValues {
  return {
    date_from: '2026-09-01',
    date_to: '2026-09-30',
    category_keys: ['gol'],
    row_mode: 'total_only',
    operator_keys: [],
    site_keys: [],
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

describe('stored payloads written before spec 0112 (AC-018)', () => {
  it('hydrates a payload without site_keys instead of discarding it', () => {
    window.localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({
        date_from: '2026-03-02',
        date_to: '2026-03-06',
        category_keys: ['gol'],
        row_mode: 'all',
        operator_keys: ['11'],
      }),
    )

    const { result } = renderHook(() => useRequestReportFilters())

    // The dates and branches the operator was working on survive...
    expect(result.current.filters.date_from).toBe('2026-03-02')
    expect(result.current.filters.category_keys).toEqual(['gol'])
    expect(result.current.filters.operator_keys).toEqual(['11'])
    // ...and the field that did not exist back then hydrates empty, for the
    // panel's own reconcile to seed from the server's list.
    expect(result.current.filters.site_keys).toEqual([])
  })

  it('still rejects a payload whose site_keys is not a list of strings', () => {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify({ ...filters(), site_keys: [7] }))

    const { result } = renderHook(() => useRequestReportFilters())

    expect(result.current.filters.row_mode).toBe('all')
  })
})

describe('reconcileSiteKeys (AC-019)', () => {
  it('returns the same object when every stored site still exists', () => {
    const current = filters({ site_keys: ['3', '7'] })

    expect(reconcileSiteKeys(current, SITES)).toBe(current)
  })

  it('drops a site the report no longer offers', () => {
    const current = filters({ site_keys: ['3', 'closed'] })

    expect(reconcileSiteKeys(current, SITES).site_keys).toEqual(['3'])
  })

  it('falls back to every site when nothing stored survives', () => {
    const current = filters({ site_keys: ['closed'] })

    expect(reconcileSiteKeys(current, SITES).site_keys).toEqual(['3', '7'])
  })

  it('seeds every site on a first, empty selection', () => {
    const current = filters({ site_keys: [] })

    expect(reconcileSiteKeys(current, SITES).site_keys).toEqual(['3', '7'])
  })

  it('returns the SAME reference when the report offers no site at all', () => {
    const current = filters({ site_keys: [] })

    expect(reconcileSiteKeys(current, [])).toBe(current)
  })
})
