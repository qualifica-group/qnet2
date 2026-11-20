import type { GridState, SortModelItem } from 'ag-grid-community'

/** Where the user stands in a grid: the active sort, the page and the page size. */
export interface GridPosition {
  sortModel: SortModelItem[]
  page: number
  pageSize: number
}

/**
 * Keeps a grid's position while it is unmounted, e.g. a record opened as a page
 * and the list reached again. Read once per mount, written on every change.
 */
export interface GridPositionStore {
  read: () => GridPosition | null
  write: (position: GridPosition) => void
}

/** The page sizes the pager offers: the config's page size and its multiples. */
export function pageSizeOptions(blockSize: number): number[] {
  return [blockSize, blockSize * 2, blockSize * 4]
}

/** The stored position, or `null` when its page size is not offered any more (the config changed). */
export function restorablePosition(position: GridPosition | null, blockSize: number): GridPosition | null {
  return position && pageSizeOptions(blockSize).includes(position.pageSize) ? position : null
}

/** The grid's position as AG Grid reports it in its state. */
export function gridPositionFromState(state: GridState, fallbackPageSize: number): GridPosition {
  return {
    sortModel: state.sort?.sortModel ?? [],
    page: state.pagination?.page ?? 0,
    pageSize: state.pagination?.pageSize ?? fallbackPageSize,
  }
}

/** The grid's starting state: the saved column filters, plus the restored sort and page. */
export function buildInitialGridState(
  filterModel: Record<string, unknown> | undefined,
  position: GridPosition | null,
): GridState | undefined {
  const state: GridState = {}
  if (filterModel && Object.keys(filterModel).length > 0) {
    state.filter = { filterModel }
  }
  if (position) {
    if (position.sortModel.length > 0) {
      state.sort = { sortModel: position.sortModel }
    }
    state.pagination = { page: position.page, pageSize: position.pageSize }
  }
  return Object.keys(state).length > 0 ? state : undefined
}

/**
 * Rows the SSRM assumes before its first response. It must reach the restored
 * page: with a single page assumed, AG Grid clamps the page back to the first
 * before the real total arrives.
 */
export function initialRowCount(pageSize: number, position: GridPosition | null): number {
  return position ? (position.page + 1) * pageSize : pageSize
}
