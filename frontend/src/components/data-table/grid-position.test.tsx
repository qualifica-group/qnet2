import type { ReactNode } from 'react'
import { render, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { GridApi, GridReadyEvent, IServerSideDatasource, IServerSideGetRowsRequest } from 'ag-grid-community'
import { describe, expect, it, vi } from 'vitest'
import { DataTable } from '@/components/data-table/data-table'
import type { GridPosition, GridPositionStore } from '@/components/data-table/grid-position'
import { UiScaleContext } from '@/features/appearance/ui-scale-context'
import type { TableColumn } from '@/features/table/types'

/**
 * User report 2026-11-10: on page 3 of a sorted list, opening a record as a page
 * and coming back, or closing a notes dialog, left the user on page 1 unsorted.
 * The grid now restores its position on mount, and a write reloads the rows in
 * place instead of purging them.
 */

const SCALE = { scale: 50, factor: 1, setScale: () => {} }
const BLOCK_SIZE = 25
const TOTAL_ROWS = 100

function Providers({ children }: { children: ReactNode }) {
  return (
    <QueryClientProvider client={new QueryClient()}>
      <UiScaleContext.Provider value={SCALE}>{children}</UiScaleContext.Provider>
    </QueryClientProvider>
  )
}

const COLUMNS = [
  {
    id: 'name',
    label: 'name',
    type: 'text',
    visible: true,
    width: null,
    order: 0,
    sortable: true,
    filterable: false,
    filterType: null,
    hasFilterValues: false,
    editable: false,
    options: null,
  },
] as unknown as TableColumn[]

/** A 100-row server: records every request, answers with the rows of the requested range. */
function fakeServer() {
  const requests: IServerSideGetRowsRequest[] = []
  const datasource: IServerSideDatasource = {
    getRows: (params) => {
      requests.push(params.request)
      const start = params.request.startRow ?? 0
      const end = Math.min(params.request.endRow ?? start + BLOCK_SIZE, TOTAL_ROWS)
      const rowData = Array.from({ length: end - start }, (_, index) => ({
        id: start + index + 1,
        actions: [],
        name: `row ${start + index + 1}`,
      }))
      params.success({ rowData, rowCount: TOTAL_ROWS })
    },
  }
  return { datasource, requests }
}

function memoryStore(initial: GridPosition | null): GridPositionStore & { saved: () => GridPosition | null } {
  let position = initial
  return {
    read: () => position,
    write: (next) => {
      position = next
    },
    saved: () => position,
  }
}

function renderGrid(datasource: IServerSideDatasource, positionStore?: GridPositionStore) {
  let api: GridApi | null = null
  render(
    <Providers>
      <DataTable
        domain="work-orders"
        columns={COLUMNS}
        datasource={datasource}
        blockSize={BLOCK_SIZE}
        positionStore={positionStore}
        onGridReady={(event: GridReadyEvent) => {
          api = event.api
        }}
      />
    </Providers>,
  )
  return { getApi: () => api as unknown as GridApi }
}

describe('DataTable — position kept across remounts', () => {
  it('reopens on the stored page with the stored sort, asking the server for that page only', async () => {
    const { datasource, requests } = fakeServer()
    const store = memoryStore({ sortModel: [{ colId: 'name', sort: 'desc' }], page: 2, pageSize: BLOCK_SIZE })
    const { getApi } = renderGrid(datasource, store)

    await waitFor(() => expect(requests.length).toBeGreaterThan(0))
    expect(requests[0].startRow).toBe(2 * BLOCK_SIZE)
    expect(requests[0].sortModel).toEqual([expect.objectContaining({ colId: 'name', sort: 'desc' })])
    await waitFor(() => expect(getApi().paginationGetCurrentPage()).toBe(2))
  })

  it('stores the page and the sort the user moves to', async () => {
    const { datasource, requests } = fakeServer()
    const store = memoryStore(null)
    const { getApi } = renderGrid(datasource, store)
    await waitFor(() => expect(requests.length).toBeGreaterThan(0))

    getApi().paginationGoToPage(1)
    getApi().applyColumnState({ state: [{ colId: 'name', sort: 'asc' }] })

    await waitFor(() =>
      expect(store.saved()).toEqual({
        sortModel: [expect.objectContaining({ colId: 'name', sort: 'asc' })],
        page: 1,
        pageSize: BLOCK_SIZE,
      }),
    )
  })

  it('ignores a stored page size the pager no longer offers', async () => {
    const { datasource, requests } = fakeServer()
    renderGrid(datasource, memoryStore({ sortModel: [], page: 2, pageSize: 30 }))

    await waitFor(() => expect(requests.length).toBeGreaterThan(0))
    expect(requests[0].startRow).toBe(0)
  })
})

describe('DataTable — refresh after a write', () => {
  async function onThirdPage() {
    const { datasource, requests } = fakeServer()
    const { getApi } = renderGrid(datasource)
    await waitFor(() => expect(requests.length).toBeGreaterThan(0))
    getApi().paginationGoToPage(2)
    await waitFor(() => expect(getApi().paginationGetCurrentPage()).toBe(2))
    return getApi()
  }

  it('keeps the page when the rows are reloaded in place', async () => {
    const api = await onThirdPage()

    api.refreshServerSide({ purge: false })

    await waitFor(() => expect(api.paginationGetCurrentPage()).toBe(2))
  })

  it('falls back to page 1 on a purge: why a write must not purge', async () => {
    const api = await onThirdPage()

    api.refreshServerSide({ purge: true })

    await waitFor(() => expect(api.paginationGetCurrentPage()).toBe(0))
  })
})

vi.setConfig({ testTimeout: 15_000 })
