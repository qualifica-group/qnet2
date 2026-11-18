import { useCallback, useMemo, useState, type RefObject } from 'react'
import type { TableViewHandle } from '@/features/table/table-view'
import type { TableRowsAggregates } from '@/features/table/types'

/** "All" tab: never a status name, since a status always has one. */
export const ALL_STATUSES_TAB = ''

type FilterModel = Record<string, unknown>

/** The tab a grid filter model corresponds to; `null` when it matches none of them. */
function readStatusTab(model: FilterModel, filterKey: string, statuses: readonly string[]): string | null {
  const values = (model[filterKey] as { values?: unknown } | undefined)?.values
  if (!Array.isArray(values) || values.length === 0) {
    return ALL_STATUSES_TAB
  }
  const only = values.length === 1 ? String(values[0]) : null
  return only !== null && statuses.includes(only) ? only : null
}

/**
 * Status tabs over a grid (specs 0397, 0409): one tab per status name, writing
 * the set filter of the `filterKey` column. The grid filter model is the
 * single source of truth (so "clear filters" and saved filters keep the tabs
 * honest); the tabs read from it and write through the table handle. The
 * counts are the grid's `meta.aggregates`, so they follow every other filter.
 */
export function useStatusFilterTabs(
  tableRef: RefObject<TableViewHandle | null>,
  filterKey: string,
  statuses: readonly string[],
) {
  const [model, setModel] = useState<FilterModel>({})
  const [counts, setCounts] = useState<TableRowsAggregates | undefined>(undefined)
  const active = useMemo(() => readStatusTab(model, filterKey, statuses), [model, filterKey, statuses])

  const select = useCallback(
    (tab: string) =>
      tableRef.current?.setFilterModel({
        [filterKey]: tab === ALL_STATUSES_TAB ? null : { filterType: 'set', values: [tab] },
      }),
    [tableRef, filterKey],
  )

  return { statuses, active, counts, select, onFilterModelChange: setModel, onAggregatesChange: setCounts }
}
