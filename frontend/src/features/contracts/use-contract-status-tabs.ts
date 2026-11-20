import { useCallback, useMemo, useState, type RefObject } from 'react'
import { useQuery } from '@tanstack/react-query'
import { fetchContractStatusesForSelect } from '@/features/contract-statuses/for-select-api'
import type { TableViewHandle } from '@/features/table/table-view'
import type { TableRowsAggregates } from '@/features/table/types'

/** Grid filter key of the status tabs: the `contract_status` set column, filtered by status name (spec 0397). */
export const CONTRACT_STATUS_FILTER_KEY = 'contract_status'
/** "All" tab: never a status name, since a status always has one. */
export const ALL_CONTRACT_STATUSES_TAB = ''
/** Every active status fits in one page: the for-select caps the page at 100. */
const STATUSES_PAGE_SIZE = 100
const CONTRACT_STATUS_TABS_QUERY_KEY = ['contract-statuses', 'status-tabs'] as const
const NO_STATUSES: string[] = []

type FilterModel = Record<string, unknown>

/** The tab a grid filter model corresponds to; `null` when it matches none of them. */
function readStatusTab(model: FilterModel, statuses: readonly string[]): string | null {
  const values = (model[CONTRACT_STATUS_FILTER_KEY] as { values?: unknown } | undefined)?.values
  if (!Array.isArray(values) || values.length === 0) {
    return ALL_CONTRACT_STATUSES_TAB
  }
  const only = values.length === 1 ? String(values[0]) : null
  return only !== null && statuses.includes(only) ? only : null
}

/**
 * The status tabs of the Contratti page, one per active contract status in the
 * configured order: the grid filter model is the single source of truth (so
 * "clear filters" and saved filters keep the tabs honest); the tabs read from
 * it and write through the table handle. The counts come with the rows, so
 * they follow every other filter of the grid.
 */
export function useContractStatusTabs(tableRef: RefObject<TableViewHandle | null>) {
  const [model, setModel] = useState<FilterModel>({})
  const [counts, setCounts] = useState<TableRowsAggregates | undefined>(undefined)
  const { data } = useQuery({
    queryKey: CONTRACT_STATUS_TABS_QUERY_KEY,
    queryFn: () => fetchContractStatusesForSelect({ limit: STATUSES_PAGE_SIZE }),
  })
  const statuses = useMemo(() => data?.items.map((item) => item.label) ?? NO_STATUSES, [data])
  const active = useMemo(() => readStatusTab(model, statuses), [model, statuses])

  const select = useCallback(
    (tab: string) =>
      tableRef.current?.setFilterModel({
        [CONTRACT_STATUS_FILTER_KEY]: tab === ALL_CONTRACT_STATUSES_TAB ? null : { filterType: 'set', values: [tab] },
      }),
    [tableRef],
  )

  return { statuses, active, counts, select, onFilterModelChange: setModel, onAggregatesChange: setCounts }
}
