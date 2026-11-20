import { useContext, useMemo } from 'react'
import type { GridPosition, GridPositionStore } from '@/components/data-table/grid-position'
import { AuthContext } from '@/features/auth/auth-context'
import type { WorkOrderTypeScope } from '@/features/table/rows-types'
import { tableLocalFiltersKey, type TableLocalFiltersScope } from '@/features/table/table-local-filters-storage'

/**
 * Each table's last position (sort, page, page size), kept in memory for the
 * life of the app: leaving the list for a record and coming back restores it,
 * a reload starts afresh on page 1.
 */
const positions = new Map<string, GridPosition>()

interface TablePositionScope extends Omit<TableLocalFiltersScope, 'userId'> {
  /** Row-set scope to one work order type (spec 0359): each type page keeps its own position. */
  workOrderType?: WorkOrderTypeScope
}

/**
 * The position store of one table instance, keyed like its browser-kept
 * filters (user, domain, row-set scope). `undefined` without a signed-in user.
 */
export function useTablePositionStore({ workOrderType, ...scope }: TablePositionScope): GridPositionStore | undefined {
  const userId = useContext(AuthContext)?.user?.id
  const key =
    userId === undefined ? null : `${tableLocalFiltersKey({ userId, ...scope })}:${workOrderType ?? ''}`

  return useMemo(() => {
    if (key === null) {
      return undefined
    }
    return {
      read: () => positions.get(key) ?? null,
      write: (position) => {
        positions.set(key, position)
      },
    }
  }, [key])
}
