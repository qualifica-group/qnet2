// @vitest-environment jsdom
import { waitFor } from '@testing-library/react'
import type {
  FilterOpenedEvent,
  GridApi,
  IMultiFilterParams,
  ISetFilterParams,
  SetFilterValuesFunc,
  SetFilterValuesFuncParams,
} from 'ag-grid-community'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { buildColumnFilter, createColumnValuesGetter } from '@/components/data-table/column-filters'
import {
  checkServerSearchMatches,
  hookSetFilterServerSearch,
  markSetFilterTruncated,
  setFilterServerSearch,
} from '@/components/data-table/set-filter-server-search'
import { fetchTableColumnValues } from '@/features/table/api'
import type { TableColumn } from '@/features/table/types'

vi.mock('@/features/table/api', () => ({
  fetchTableColumnValues: vi.fn(),
}))

const fetchValuesMock = vi.mocked(fetchTableColumnValues)

/** A grid whose `columnId` filter is a Multi Filter (Set first) or a standalone Set Filter. */
function stubApi(miniFilter: string | null, multi = true) {
  const refreshFilterValues = vi.fn()
  const setMiniFilter = vi.fn()
  const setUi = { getMiniFilter: () => miniFilter, setMiniFilter }
  const setHandler = { refreshFilterValues }
  const api = {
    getFilterModel: () => ({}),
    getGridOption: () => undefined,
    getColumnFilterInstance: vi.fn(async () => (multi ? { getChildFilterInstance: () => setUi } : setUi)),
    getColumnFilterHandler: vi.fn(() => (multi ? { getHandler: () => setHandler } : setHandler)),
  }
  return { api: api as unknown as GridApi, instanceLookup: api.getColumnFilterInstance, refreshFilterValues, setMiniFilter }
}

function openFilter(api: GridApi, columnId: string): HTMLInputElement {
  const eGui = document.createElement('div')
  eGui.innerHTML = '<div class="ag-set-filter"><div class="ag-mini-filter"><input /></div></div>'
  const event = { api, column: { getColId: () => columnId }, eGui } as unknown as FilterOpenedEvent
  hookSetFilterServerSearch(event)
  hookSetFilterServerSearch(event)
  return eGui.querySelector('input') as HTMLInputElement
}

describe('setFilterServerSearch', () => {
  it('keeps the search client-side while the column list is complete', async () => {
    const { api, instanceLookup } = stubApi('ferro')

    await expect(setFilterServerSearch(api, 'registry')).resolves.toBeNull()
    expect(instanceLookup).not.toHaveBeenCalled()
  })

  it.each([true, false])('sends the search box text of a truncated column (multi: %s)', async (multi) => {
    const { api } = stubApi('  ferro ', multi)
    markSetFilterTruncated(api, 'registry')

    await expect(setFilterServerSearch(api, 'registry')).resolves.toBe('ferro')
  })

  it('sends nothing for an empty search box', async () => {
    const { api } = stubApi('   ')
    markSetFilterTruncated(api, 'registry')

    await expect(setFilterServerSearch(api, 'registry')).resolves.toBeNull()
  })
})

describe('checkServerSearchMatches', () => {
  it('re-runs the search box so the reloaded matches get checked', async () => {
    const { api, setMiniFilter } = stubApi('ferro')

    await checkServerSearchMatches(api, 'registry')

    expect(setMiniFilter.mock.calls).toEqual([[null], ['ferro']])
  })

  it('leaves an empty search box alone', async () => {
    const { api, setMiniFilter } = stubApi(null)

    await checkServerSearchMatches(api, 'registry')

    expect(setMiniFilter).not.toHaveBeenCalled()
  })
})

describe('hookSetFilterServerSearch', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('reloads a truncated list once the user stops typing', () => {
    const { api, refreshFilterValues } = stubApi('fer')
    markSetFilterTruncated(api, 'code')
    const input = openFilter(api, 'code')

    input.dispatchEvent(new Event('input'))
    input.dispatchEvent(new Event('input'))
    vi.advanceTimersByTime(300)

    expect(refreshFilterValues).toHaveBeenCalledOnce()
  })

  it('never reloads a complete list', () => {
    const { api, refreshFilterValues } = stubApi('fer')
    const input = openFilter(api, 'type')

    input.dispatchEvent(new Event('input'))
    vi.advanceTimersByTime(300)

    expect(refreshFilterValues).not.toHaveBeenCalled()
  })
})

// Task 427169: "Commessa n." / "Anagrafica" on /work-orders, more values than the first page.
describe('createColumnValuesGetter with a truncated list', () => {
  beforeEach(() => {
    fetchValuesMock.mockReset()
  })

  it('asks the server for the searched values after the first truncated load', async () => {
    fetchValuesMock.mockResolvedValue({ values: ['COM-10000'], hasMore: true })
    const { api, setMiniFilter } = stubApi('20265')
    const getter = createColumnValuesGetter('work-orders', 'code', vi.fn())
    const firstLoad = { api, success: vi.fn() } as unknown as SetFilterValuesFuncParams

    getter(firstLoad)
    await waitFor(() => expect(firstLoad.success).toHaveBeenCalled())
    expect(fetchValuesMock).toHaveBeenLastCalledWith('work-orders', { columnId: 'code', filterModel: {} })
    expect(setMiniFilter).not.toHaveBeenCalled()

    fetchValuesMock.mockResolvedValue({ values: ['COM-20265'], hasMore: false })
    const searched = { api, success: vi.fn() } as unknown as SetFilterValuesFuncParams
    getter(searched)
    await waitFor(() => expect(searched.success).toHaveBeenCalledWith(['COM-20265']))

    expect(fetchValuesMock).toHaveBeenLastCalledWith('work-orders', { columnId: 'code', filterModel: {}, search: '20265' })
    await waitFor(() => expect(setMiniFilter).toHaveBeenLastCalledWith('20265'))
  })

  // Task 427789: "Data inizio" lists dates as 15/01/2026, the server stores 2026-01-15.
  it('sends a date column search in the stored date form', async () => {
    const { api } = stubApi('01/2026')
    markSetFilterTruncated(api, 'start_date')
    fetchValuesMock.mockResolvedValue({ values: ['2026-01-15 00:00:00'], hasMore: false })
    const { filterParams } = buildColumnFilter(
      'work-orders',
      // The backend serves `type: 'date'`, absent from the frontend ColumnType union.
      { id: 'start_date', label: 'label', type: 'date' as TableColumn['type'], filterType: 'date', visible: true, width: null, order: 0, sortable: true, filterable: true },
      vi.fn(),
      (key) => key,
    )
    const setParams = (filterParams as IMultiFilterParams).filters?.[0]?.filterParams as ISetFilterParams
    const getValues = setParams.values as SetFilterValuesFunc
    const load = { api, success: vi.fn() } as unknown as SetFilterValuesFuncParams

    getValues(load)
    await waitFor(() => expect(load.success).toHaveBeenCalledWith(['2026-01-15 00:00:00']))

    expect(fetchValuesMock).toHaveBeenLastCalledWith('work-orders', { columnId: 'start_date', filterModel: {}, search: '2026-01' })
  })
})
