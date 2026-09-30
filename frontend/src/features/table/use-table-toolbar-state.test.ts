// @vitest-environment jsdom
import { act, renderHook } from '@testing-library/react'
import type { GridApi } from 'ag-grid-community'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { appliedSearchTerm, useTableToolbarState } from '@/features/table/use-table-toolbar-state'

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
