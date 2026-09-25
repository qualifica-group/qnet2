import { useMemo, useState } from 'react'
import { useTableConfig } from '@/features/table/use-table-config'
import { useAdvancedFilters } from '@/features/table/advanced-filters/use-advanced-filters'
import { TASKS_DOMAIN } from '@/features/tasks/api'
import type { AdvancedFilterDescriptor } from '@/features/table/advanced-filters/types'
import type { SsrmSortModelItem } from '@/features/table/types'

const EMPTY_DESCRIPTORS: AdvancedFilterDescriptor[] = []

function noop(): void {}

/**
 * Owns the Kanban's own filter slice (spec 0164, replacing the single-block
 * fetch of spec 0157 D-4): the SAME advanced filters, column filters and
 * search the Analitica/Sintetica list uses. No longer fetches rows itself —
 * each column loads its OWN block via `useTaskKanbanColumnRows`, reading
 * these params. Search is local/ephemeral like the list's own toolbar
 * search; advanced filters are the domain's PERSISTED ones
 * (`useAdvancedFilters`, same store the list reads/writes), so applying them
 * here also updates the list. Column filters (`filterModel`) are read-only
 * here — there is no grid to change them from — but the persisted ones still
 * narrow every column's rows, for parity with the list.
 */
export function useTaskKanbanFilters() {
  const { data: config, isPending, isError, refetch } = useTableConfig(TASKS_DOMAIN)
  const [search, setSearch] = useState('')

  const descriptors = config?.advancedFilters ?? EMPTY_DESCRIPTORS
  const advancedFilters = useAdvancedFilters({
    domain: TASKS_DOMAIN,
    descriptors,
    applied: config?.appliedAdvancedFilters,
    // Every column's own query key already includes `activeValues`, so React
    // Query refetches each of them on its own the moment Apply/Reset changes
    // it — no imperative refresh needed here.
    onApplied: noop,
  })

  const sortModel: SsrmSortModelItem[] = useMemo(
    () => (config?.defaultSort ?? []).map((sort) => ({ colId: sort.columnId, sort: sort.direction })),
    [config?.defaultSort],
  )
  const filterModel = config?.filterState ?? {}
  const trimmedSearch = search.trim()

  return {
    /** Whether the config every column's query depends on has loaded — gates each column's `enabled`. */
    isReady: config !== undefined,
    isPending,
    isError,
    refetch,
    search,
    setSearch,
    descriptors,
    advancedFilters,
    sortModel,
    filterModel,
    trimmedSearch,
  }
}

export type UseTaskKanbanFiltersResult = ReturnType<typeof useTaskKanbanFilters>
