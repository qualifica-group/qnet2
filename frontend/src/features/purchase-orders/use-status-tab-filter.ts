import { useCallback, useMemo, useState, type RefObject } from 'react'
import type { TableViewHandle } from '@/features/table/table-view'
import { isPurchaseOrderStatus, type PurchaseOrderStatus } from '@/features/purchase-orders/types'

export const ALL_TAB = 'all'
export type StatusTab = PurchaseOrderStatus | typeof ALL_TAB

type FilterModel = Record<string, unknown>

/** Active tab from the grid filter model: exactly one known status value, else "all". */
function readTab(model: FilterModel, filterKey: string): StatusTab {
  const values = (model[filterKey] as { values?: unknown } | undefined)?.values
  const only = Array.isArray(values) && values.length === 1 ? String(values[0]) : null
  return isPurchaseOrderStatus(only) ? only : ALL_TAB
}

/**
 * Status tabs over the ODA grid: the grid filter model is the single source of
 * truth (so "clear filters" and saved filters keep the tabs honest); the tabs
 * read from it and write through the table handle.
 */
export function useStatusTabFilter(tableRef: RefObject<TableViewHandle | null>, filterKey: string) {
  const [model, setModel] = useState<FilterModel>({})
  const active = useMemo(() => readTab(model, filterKey), [model, filterKey])

  const select = useCallback(
    (tab: StatusTab) =>
      tableRef.current?.setFilterModel({
        [filterKey]: tab === ALL_TAB ? null : { filterType: 'set', values: [tab] },
      }),
    [tableRef, filterKey],
  )

  return { active, select, onFilterModelChange: setModel }
}
