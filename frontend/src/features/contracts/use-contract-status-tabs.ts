import { useMemo, type RefObject } from 'react'
import { useQuery } from '@tanstack/react-query'
import { fetchContractStatusesForSelect } from '@/features/contract-statuses/for-select-api'
import type { TableViewHandle } from '@/features/table/table-view'
import { useStatusFilterTabs } from '@/features/table/use-status-filter-tabs'

/** Grid filter key of the status tabs: the `contract_status` set column, filtered by status name (spec 0397). */
export const CONTRACT_STATUS_FILTER_KEY = 'contract_status'
/** Every active status fits in one page: the for-select caps the page at 100. */
const STATUSES_PAGE_SIZE = 100
const CONTRACT_STATUS_TABS_QUERY_KEY = ['contract-statuses', 'status-tabs'] as const
const NO_STATUSES: string[] = []

/** The status tabs of the Contratti page, one per active contract status in the configured order. */
export function useContractStatusTabs(tableRef: RefObject<TableViewHandle | null>) {
  const { data } = useQuery({
    queryKey: CONTRACT_STATUS_TABS_QUERY_KEY,
    queryFn: () => fetchContractStatusesForSelect({ limit: STATUSES_PAGE_SIZE }),
  })
  const statuses = useMemo(() => data?.items.map((item) => item.label) ?? NO_STATUSES, [data])

  return useStatusFilterTabs(tableRef, CONTRACT_STATUS_FILTER_KEY, statuses)
}
