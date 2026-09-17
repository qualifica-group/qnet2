import type { PaginationChangedEvent } from 'ag-grid-community'
import type { TableRow } from '@/features/table/types'

/**
 * `paginationChanged` handler that keeps the SSRM `cacheBlockSize` equal to the
 * page size the user picks in the page-size selector.
 *
 * The SSRM fetches one block per `/rows` request, and the block starts at the
 * backend's default page size (25). Picking a larger page left the block
 * untouched, so a 100-row page was fetched in four requests fired one after
 * another while scrolling. Setting `cacheBlockSize` resets the store (AG Grid's
 * own listener), so the page reloads once in a single request. The equality
 * guard keeps the `paginationChanged` emitted by that reset from resetting again.
 */
export function syncCacheBlockToPageSize(event: PaginationChangedEvent<TableRow>): void {
  if (!event.newPageSize) {
    return
  }
  const pageSize = event.api.paginationGetPageSize()
  if (event.api.getGridOption('cacheBlockSize') !== pageSize) {
    event.api.setGridOption('cacheBlockSize', pageSize)
  }
}
