import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import type { ColumnState, GridApi } from 'ag-grid-community'
import { useTableLayoutPersistence } from '@/features/table/use-table-layout-persistence'
import { saveTableFilters, saveTablePreferences } from '@/features/table/api'
import type { TableConfig } from '@/features/table/types'

vi.mock('@/features/table/api', () => ({
  saveTablePreferences: vi.fn(),
  saveTableFilters: vi.fn(),
  resetTablePreferences: vi.fn(),
  resetTableFilters: vi.fn(),
}))

const savePreferencesMock = vi.mocked(saveTablePreferences)
const saveFiltersMock = vi.mocked(saveTableFilters)

const CONFIG = { resource: 'users', columns: [] } as unknown as TableConfig
const COLUMN_STATE: ColumnState[] = [
  { colId: 'email', hide: false, width: 200 },
  { colId: 'name', hide: true, width: 120 },
]
const EXPECTED_COLUMNS = [
  { id: 'email', visible: true, order: 0, width: 200 },
  { id: 'name', visible: false, order: 1, width: 120 },
]
const FILTER_MODEL = { email: { filterType: 'text', type: 'contains', filter: 'a' } }
const KNOWN_COLUMN_IDS = new Set(['email', 'name'])
const NO_FILTERS: Record<string, unknown> = {}

const gridApi = {
  getColumnState: () => COLUMN_STATE,
  getFilterModel: () => FILTER_MODEL,
  isDestroyed: () => false,
} as unknown as GridApi

// What AG Grid hands back once a grid is gone: every call returns undefined.
const destroyedGridApi = {
  getColumnState: () => undefined,
  getFilterModel: () => undefined,
  isDestroyed: () => true,
} as unknown as GridApi

function wrapper() {
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderPersistence(api: GridApi = gridApi) {
  return renderHook(
    () =>
      useTableLayoutPersistence({
        domain: 'users',
        scope: { productCategoryId: 12 },
        gridApi: api,
        knownColumnIds: KNOWN_COLUMN_IDS,
        initialFilterModel: NO_FILTERS,
        configCustomized: false,
        configFiltersCustomized: false,
        refetchConfig: () => Promise.resolve(),
      }),
    { wrapper: wrapper() },
  )
}

beforeEach(() => {
  savePreferencesMock.mockReset()
  savePreferencesMock.mockResolvedValue(CONFIG)
  saveFiltersMock.mockReset()
  saveFiltersMock.mockResolvedValue(CONFIG)
})

afterEach(() => {
  vi.useRealTimers()
})

describe('useTableLayoutPersistence', () => {
  it('saves the layout once after the debounce window', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    const { result } = renderPersistence()

    act(() => {
      result.current.handleColumnStateChanged()
      result.current.handleColumnStateChanged()
    })
    expect(savePreferencesMock).not.toHaveBeenCalled()

    act(() => {
      vi.advanceTimersByTime(500)
    })
    vi.useRealTimers()

    await waitFor(() => expect(savePreferencesMock).toHaveBeenCalledTimes(1))
    expect(savePreferencesMock).toHaveBeenCalledWith('users', EXPECTED_COLUMNS, 12)
  })

  // The reported bug: change a column, reload at once — the debounced save was dropped.
  it('sends a pending layout change as a keepalive request when the page is unloaded', () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    const { result } = renderPersistence()

    act(() => {
      result.current.handleColumnStateChanged()
    })
    window.dispatchEvent(new Event('pagehide'))

    expect(savePreferencesMock).toHaveBeenCalledTimes(1)
    expect(savePreferencesMock).toHaveBeenCalledWith('users', EXPECTED_COLUMNS, 12, { keepalive: true })

    // The debounce no longer fires a second, duplicate save.
    act(() => {
      vi.advanceTimersByTime(500)
    })
    expect(savePreferencesMock).toHaveBeenCalledTimes(1)
  })

  it('sends a pending filter change as a keepalive request when the page is unloaded', () => {
    const { result } = renderPersistence()

    act(() => {
      result.current.handleFilterChanged()
    })
    window.dispatchEvent(new Event('pagehide'))

    expect(saveFiltersMock).toHaveBeenCalledWith('users', { filterModel: FILTER_MODEL }, 12, { keepalive: true })
  })

  it('does not send anything on unload when no change is pending', () => {
    renderPersistence()

    window.dispatchEvent(new Event('pagehide'))

    expect(savePreferencesMock).not.toHaveBeenCalled()
    expect(saveFiltersMock).not.toHaveBeenCalled()
  })

  it('flushes a pending layout change on unmount instead of dropping it', async () => {
    const { result, unmount } = renderPersistence()

    act(() => {
      result.current.handleColumnStateChanged()
    })
    unmount()

    await waitFor(() => expect(savePreferencesMock).toHaveBeenCalledTimes(1))
    expect(savePreferencesMock).toHaveBeenCalledWith('users', EXPECTED_COLUMNS, 12)
  })

  it('ignores a filter change read off a destroyed grid, keeping the filter model an object', () => {
    const { result } = renderPersistence(destroyedGridApi)

    act(() => {
      result.current.handleFilterChanged()
    })
    window.dispatchEvent(new Event('pagehide'))

    expect(result.current.filterModel).toEqual(NO_FILTERS)
    expect(saveFiltersMock).not.toHaveBeenCalled()
  })

  it('keeps the live filter model when a config refresh (its own save echo) changes the initial model', () => {
    const forced = { status: { filterType: 'set', values: ['unpaid'] } }
    const { result, rerender } = renderHook(
      ({ initialFilterModel }) =>
        useTableLayoutPersistence({
          domain: 'users',
          scope: { productCategoryId: 12 },
          gridApi,
          knownColumnIds: KNOWN_COLUMN_IDS,
          initialFilterModel,
          configCustomized: false,
          configFiltersCustomized: false,
          refetchConfig: () => Promise.resolve(),
        }),
      { wrapper: wrapper(), initialProps: { initialFilterModel: NO_FILTERS } },
    )

    act(() => {
      result.current.handleFilterChanged()
    })
    rerender({ initialFilterModel: forced })

    expect(result.current.filterModel).toEqual(FILTER_MODEL)
  })

  it('resyncs the filter model to the initial one when a new grid mounts', () => {
    const forced = { status: { filterType: 'set', values: ['unpaid'] } }
    const remounted = { ...gridApi } as unknown as GridApi
    const { result, rerender } = renderHook(
      ({ api, initialFilterModel }) =>
        useTableLayoutPersistence({
          domain: 'users',
          scope: { productCategoryId: 12 },
          gridApi: api,
          knownColumnIds: KNOWN_COLUMN_IDS,
          initialFilterModel,
          configCustomized: false,
          configFiltersCustomized: false,
          refetchConfig: () => Promise.resolve(),
        }),
      { wrapper: wrapper(), initialProps: { api: gridApi, initialFilterModel: NO_FILTERS } },
    )

    act(() => {
      result.current.handleFilterChanged()
    })
    rerender({ api: remounted, initialFilterModel: forced })

    expect(result.current.filterModel).toEqual(forced)
  })
})
