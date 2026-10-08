import { useCallback, useSyncExternalStore } from 'react'
import type { GridApi } from 'ag-grid-community'

const ROW_GROUP_EVENT = 'columnRowGroupChanged'
const SNAPSHOT_SEPARATOR = '\u0000'
const NO_GROUPS: string[] = []

/**
 * Next ordered list of grouped column ids after ticking/unticking `columnId`:
 * a new level is appended (it nests under the existing ones), never past
 * `maxDepth`; removing a level keeps the order of the others.
 */
export function toggleRowGroupColumn(current: string[], columnId: string, maxDepth: number): string[] {
  if (current.includes(columnId)) {
    return current.filter((id) => id !== columnId)
  }
  return current.length >= maxDepth ? current : [...current, columnId]
}

export interface RowGroupColumnsState {
  /** Grouped column ids, outermost level first. */
  groupedIds: string[]
  toggle: (columnId: string) => void
  remove: (columnId: string) => void
  clear: () => void
}

/**
 * The grid's row group columns as React state (spec 0197): the grid is the
 * source of truth, so a level added from the columns tool panel shows here
 * too. Read through `useSyncExternalStore` on `columnRowGroupChanged`; the
 * snapshot is a joined string so it stays referentially stable between reads.
 */
export function useRowGroupColumns(gridApi: GridApi | null, maxDepth: number): RowGroupColumnsState {
  const subscribe = useCallback(
    (onChange: () => void) => {
      if (!gridApi || gridApi.isDestroyed()) {
        return () => undefined
      }
      gridApi.addEventListener(ROW_GROUP_EVENT, onChange)
      return () => {
        if (!gridApi.isDestroyed()) {
          gridApi.removeEventListener(ROW_GROUP_EVENT, onChange)
        }
      }
    },
    [gridApi],
  )
  const getSnapshot = useCallback(
    () =>
      gridApi && !gridApi.isDestroyed()
        ? gridApi.getRowGroupColumns().map((column) => column.getColId()).join(SNAPSHOT_SEPARATOR)
        : '',
    [gridApi],
  )
  const snapshot = useSyncExternalStore(subscribe, getSnapshot, getSnapshot)
  const groupedIds = snapshot === '' ? NO_GROUPS : snapshot.split(SNAPSHOT_SEPARATOR)

  const apply = useCallback((ids: string[]) => gridApi?.setRowGroupColumns(ids), [gridApi])
  const toggle = useCallback(
    (columnId: string) => apply(toggleRowGroupColumn(groupedIds, columnId, maxDepth)),
    [apply, groupedIds, maxDepth],
  )
  const remove = useCallback(
    (columnId: string) => apply(groupedIds.filter((id) => id !== columnId)),
    [apply, groupedIds],
  )
  const clear = useCallback(() => apply([]), [apply])

  return { groupedIds, toggle, remove, clear }
}
