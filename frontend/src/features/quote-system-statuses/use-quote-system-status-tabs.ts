import { useMemo, type RefObject } from 'react'
import type { TableViewHandle } from '@/features/table/table-view'
import { useStatusFilterTabs } from '@/features/table/use-status-filter-tabs'
import { useQuoteSystemStatusOptions } from '@/features/quote-system-statuses/use-quote-system-status-options'

/** Grid filter key of the tabs: the `quote_system_status` set column of Offerte and Opportunita', by name (spec 0409). */
export const QUOTE_SYSTEM_STATUS_FILTER_KEY = 'quote_system_status'

/** The system status tabs of the Offerte and Opportunita' pages, one per system status in the configured order. */
export function useQuoteSystemStatusTabs(tableRef: RefObject<TableViewHandle | null>) {
  const { options } = useQuoteSystemStatusOptions()
  const statuses = useMemo(() => options.map((option) => option.label), [options])

  return useStatusFilterTabs(tableRef, QUOTE_SYSTEM_STATUS_FILTER_KEY, statuses)
}
