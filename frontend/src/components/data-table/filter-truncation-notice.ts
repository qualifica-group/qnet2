import type { FilterOpenedEvent } from 'ag-grid-community'

export interface FilterTruncationNotice {
  /** Wire to the grid's `onFilterOpened`: records the columns the user opened. */
  markOpened: (event: FilterOpenedEvent) => void
  /** Called when a column's Set Filter value list came back truncated. */
  notifyTruncated: (columnId: string) => void
}

/**
 * Decides when the "only the first values are listed" notice is shown, so it
 * stays a hint instead of noise. The Set Filter loads its values also when the
 * grid restores a saved filter model (page reload, saved view) and on every
 * popup open (`refreshValuesOnOpen`), and a column's own filter never narrows
 * its list: without this gate the notice fired on every reload and every open.
 * Shown only for a column the user actually opened, once per column for the
 * grid's lifetime.
 */
export function createFilterTruncationNotice(show: () => void): FilterTruncationNotice {
  const openedColumnIds = new Set<string>()
  const notifiedColumnIds = new Set<string>()

  return {
    markOpened: (event) => {
      // A column group has no filter of its own: only real columns count.
      if ('getColId' in event.column) {
        openedColumnIds.add(event.column.getColId())
      }
    },
    notifyTruncated: (columnId) => {
      if (!openedColumnIds.has(columnId) || notifiedColumnIds.has(columnId)) {
        return
      }
      notifiedColumnIds.add(columnId)
      show()
    },
  }
}
