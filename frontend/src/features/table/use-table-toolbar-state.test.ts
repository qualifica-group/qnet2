// @vitest-environment jsdom
import { act, renderHook } from '@testing-library/react'
import type { GridApi } from 'ag-grid-community'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { appliedSearchTerm, useTableToolbarState } from '@/features/table/use-table-toolbar-state'
import { advancedFiltersOpenKey } from '@/features/table/table-advanced-filters-open-storage'

const DEBOUNCE_MS = 350

function renderToolbar(searchMinLength?: number, initialSearch = '') {
  const refreshServerSide = vi.fn()
  const gridApi = { refreshServerSide } as unknown as GridApi
  const hook = renderHook(() =>
    useTableToolbarState({ gridApi, searchEnabled: true, initialSearch, searchMinLength }),
  )
  return { hook, refreshServerSide }
}

function type(hook: ReturnType<typeof renderToolbar>['hook'], value: string) {
  act(() => hook.result.current.setSearchInput(value))
  act(() => vi.advanceTimersByTime(DEBOUNCE_MS))
}

describe('appliedSearchTerm', () => {
  it('trims and empties a term below the minimum', () => {
    expect(appliedSearchTerm('  ro ', 3)).toBe('')
    expect(appliedSearchTerm(' ros ', 3)).toBe('ros')
    expect(appliedSearchTerm('a', 1)).toBe('a')
  })
})

describe('useTableToolbarState — minimum search length (spec 0179)', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('does not reload the grid until the term reaches the minimum', () => {
    const { hook, refreshServerSide } = renderToolbar(3)

    type(hook, 'ro')
    expect(refreshServerSide).not.toHaveBeenCalled()
    expect(hook.result.current.getSearchTerm()).toBe('')

    type(hook, 'ros')
    expect(refreshServerSide).toHaveBeenCalledTimes(1)
    expect(hook.result.current.getSearchTerm()).toBe('ros')
  })

  it('goes back to no search when the term drops below the minimum', () => {
    const { hook, refreshServerSide } = renderToolbar(3)

    type(hook, 'ros')
    type(hook, 'ro')

    expect(refreshServerSide).toHaveBeenCalledTimes(2)
    expect(hook.result.current.getSearchTerm()).toBe('')
  })

  it('sends any non-empty term when the domain declares no minimum', () => {
    const { hook, refreshServerSide } = renderToolbar()

    type(hook, 'a')

    expect(refreshServerSide).toHaveBeenCalledTimes(1)
    expect(hook.result.current.getSearchTerm()).toBe('a')
  })

  it('never exposes a restored term shorter than the minimum', () => {
    const { hook } = renderToolbar(3, 'ro')

    expect(hook.result.current.getSearchTerm()).toBe('')
  })
})

describe('useTableToolbarState — advanced-filters panel open state', () => {
  const KEY = advancedFiltersOpenKey(7, 'leads')

  function renderWithKey(advancedFiltersOpenKey: string | null) {
    return renderHook(
      ({ key }) => useTableToolbarState({ gridApi: null, searchEnabled: false, advancedFiltersOpenKey: key }),
      { initialProps: { key: advancedFiltersOpenKey } },
    )
  }

  beforeEach(() => {
    window.localStorage.clear()
  })

  it('starts closed when the user never opened the panel', () => {
    expect(renderWithKey(KEY).result.current.advancedFiltersOpen).toBe(false)
  })

  it('remembers an opened panel on the next mount, and forgets it once closed', () => {
    const first = renderWithKey(KEY)
    act(() => first.result.current.toggleAdvancedFilters())
    expect(first.result.current.advancedFiltersOpen).toBe(true)
    first.unmount()

    const second = renderWithKey(KEY)
    expect(second.result.current.advancedFiltersOpen).toBe(true)

    act(() => second.result.current.toggleAdvancedFilters())
    second.unmount()
    expect(window.localStorage.getItem(KEY)).toBeNull()
    expect(renderWithKey(KEY).result.current.advancedFiltersOpen).toBe(false)
  })

  it('keeps each table apart and re-reads when the key changes', () => {
    window.localStorage.setItem(advancedFiltersOpenKey(7, 'tasks'), '1')
    const hook = renderWithKey(KEY)
    expect(hook.result.current.advancedFiltersOpen).toBe(false)

    hook.rerender({ key: advancedFiltersOpenKey(7, 'tasks') })
    expect(hook.result.current.advancedFiltersOpen).toBe(true)
  })

  it('toggles without persisting when there is no key', () => {
    const hook = renderWithKey(null)
    act(() => hook.result.current.toggleAdvancedFilters())

    expect(hook.result.current.advancedFiltersOpen).toBe(true)
    expect(window.localStorage.length).toBe(0)
  })
})
