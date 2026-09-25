import { useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchTableRows } from '@/features/table/api'
import { useTableConfig } from '@/features/table/use-table-config'
import { useAdvancedFilters } from '@/features/table/advanced-filters/use-advanced-filters'
import { asTaskKanbanRow, type TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'
import { TASKS_DOMAIN } from '@/features/tasks/api'
import type { AdvancedFilterDescriptor } from '@/features/table/advanced-filters/types'
import type { SsrmSortModelItem } from '@/features/table/types'

/** Spec 0157 D-4: the Kanban loads up to this many tasks; beyond it, the caller shows "restringi i filtri". */
export const TASK_KANBAN_ROW_LIMIT = 500

const EMPTY_DESCRIPTORS: AdvancedFilterDescriptor[] = []

function noop(): void {}

/**
 * Owns the Kanban's own data slice (spec 0157 D-4): the SAME advanced
 * filters, column filters and search the Analitica/Sintetica list uses,
 * loaded in one block (`endRow` 500, no infinite scroll — out of scope) via
 * the generic `POST /tables/tasks/rows`, never a dedicated endpoint. Search
 * is local/ephemeral like the list's own toolbar search; advanced filters are
 * the domain's PERSISTED ones (`useAdvancedFilters`, same store the list
 * reads/writes), so applying them here also updates the list. Column filters
 * (`filterModel`) are read-only here — there is no grid to change them from —
 * but the persisted ones still narrow the rows, for parity with the list.
 */
export function useTaskKanbanRows() {
  const queryClient = useQueryClient()
  const { data: config } = useTableConfig(TASKS_DOMAIN)
  const [search, setSearch] = useState('')

  const descriptors = config?.advancedFilters ?? EMPTY_DESCRIPTORS
  const advancedFilters = useAdvancedFilters({
    domain: TASKS_DOMAIN,
    descriptors,
    applied: config?.appliedAdvancedFilters,
    // The query key below already includes `activeValues`, so React Query
    // refetches on its own the moment Apply/Reset changes it — no imperative
    // refresh needed here.
    onApplied: noop,
  })

  const sortModel: SsrmSortModelItem[] = useMemo(
    () => (config?.defaultSort ?? []).map((sort) => ({ colId: sort.columnId, sort: sort.direction })),
    [config?.defaultSort],
  )
  const filterModel = config?.filterState ?? {}
  const trimmedSearch = search.trim()

  const query = useQuery({
    queryKey: [
      'tasks',
      'kanban',
      'rows',
      trimmedSearch,
      advancedFilters.activeValues,
      filterModel,
      sortModel,
    ] as const,
    queryFn: () =>
      fetchTableRows(TASKS_DOMAIN, {
        startRow: 0,
        endRow: TASK_KANBAN_ROW_LIMIT,
        sortModel,
        filterModel,
        ...(trimmedSearch !== '' ? { search: trimmedSearch } : {}),
        ...(Object.keys(advancedFilters.activeValues).length > 0
          ? { advancedFilters: advancedFilters.activeValues }
          : {}),
      }),
    enabled: config !== undefined,
  })

  const rows: TaskKanbanRow[] = useMemo(
    () => (query.data?.items ?? []).map(asTaskKanbanRow),
    [query.data?.items],
  )
  const exceededLimit = (query.data?.pagination.total ?? 0) > TASK_KANBAN_ROW_LIMIT

  const refresh = () =>
    void queryClient.invalidateQueries({ queryKey: ['tasks', 'kanban', 'rows'] })

  return {
    rows,
    total: query.data?.pagination.total ?? 0,
    exceededLimit,
    isPending: query.isPending,
    isError: query.isError,
    refetch: query.refetch,
    refresh,
    search,
    setSearch,
    descriptors,
    advancedFilters,
  }
}

export type UseTaskKanbanRowsResult = ReturnType<typeof useTaskKanbanRows>
