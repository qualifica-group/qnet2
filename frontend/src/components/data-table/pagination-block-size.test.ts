import { describe, expect, it, vi } from 'vitest'
import type { GridApi, PaginationChangedEvent } from 'ag-grid-community'
import { syncCacheBlockToPageSize } from '@/components/data-table/pagination-block-size'
import type { TableRow } from '@/features/table/types'

/**
 * `syncCacheBlockToPageSize` is a pure `paginationChanged` handler, so a stub
 * of the three grid-api members it reads/writes is enough — no `AgGridReact`.
 */
function event(newPageSize: boolean, pageSize: number, cacheBlockSize: number) {
  const api = {
    paginationGetPageSize: () => pageSize,
    getGridOption: () => cacheBlockSize,
    setGridOption: vi.fn(),
  }
  const paginationEvent = {
    newPageSize,
    api: api as unknown as GridApi<TableRow>,
  } as PaginationChangedEvent<TableRow>
  return { paginationEvent, setGridOption: api.setGridOption }
}

describe('syncCacheBlockToPageSize', () => {
  it('aligns the SSRM block to a larger page so the page loads in one request', () => {
    const { paginationEvent, setGridOption } = event(true, 100, 25)

    syncCacheBlockToPageSize(paginationEvent)

    expect(setGridOption).toHaveBeenCalledWith('cacheBlockSize', 100)
  })

  it('aligns the block back down when a smaller page size is chosen', () => {
    const { paginationEvent, setGridOption } = event(true, 25, 100)

    syncCacheBlockToPageSize(paginationEvent)

    expect(setGridOption).toHaveBeenCalledWith('cacheBlockSize', 25)
  })

  it('does nothing when the block already matches (no store reset, no reload loop)', () => {
    const { paginationEvent, setGridOption } = event(true, 50, 50)

    syncCacheBlockToPageSize(paginationEvent)

    expect(setGridOption).not.toHaveBeenCalled()
  })

  it('ignores page navigation and row-count updates', () => {
    const { paginationEvent, setGridOption } = event(false, 100, 25)

    syncCacheBlockToPageSize(paginationEvent)

    expect(setGridOption).not.toHaveBeenCalled()
  })
})
