import { useInfiniteQuery } from '@tanstack/react-query'
import { fetchTableRows } from '@/features/table/api'
import { TASKS_DOMAIN } from '@/features/tasks/api'
import type { KanbanGroupParam } from '@/features/tasks/task-kanban/task-kanban-group-param'
import type { AdvancedFilterValues } from '@/features/table/advanced-filters/types'
import type { SsrmSortModelItem, TableRowsResponse } from '@/features/table/types'

/** Spec 0164 D-1: each column loads its own rows in blocks of this size (replaces the old single 500-row cap, D-4). */
export const TASK_KANBAN_COLUMN_PAGE_SIZE = 50

/**
 * One column's own query key prefix (spec 0164 D-3): invalidating exactly
 * this — the domain segments plus `kanbanGroup`, nothing else — reloads
 * ONLY this column, regardless of the currently-active filters/search/sort
 * appended after it.
 */
export function taskKanbanColumnQueryKey(kanbanGroup: KanbanGroupParam) {
  return ['tasks', 'kanban', 'column', kanbanGroup] as const
}

export interface UseTaskKanbanColumnRowsArgs {
  kanbanGroup: KanbanGroupParam
  search: string
  advancedFilters: AdvancedFilterValues
  filterModel: Record<string, unknown>
  sortModel: SsrmSortModelItem[]
  /** `useTaskKanbanFilters().isReady` — the shared config every column depends on. */
  enabled: boolean
}

/**
 * One Kanban column's own rows (spec 0164 D-1/D-2): the SAME advanced
 * filters, column filters, search and sort the list and every other column
 * use, scoped server-side to THIS column alone via `kanbanGroup`, loaded in
 * blocks of `TASK_KANBAN_COLUMN_PAGE_SIZE` as the caller scrolls.
 */
export function useTaskKanbanColumnRows({
  kanbanGroup,
  search,
  advancedFilters,
  filterModel,
  sortModel,
  enabled,
}: UseTaskKanbanColumnRowsArgs) {
  return useInfiniteQuery({
    queryKey: [
      ...taskKanbanColumnQueryKey(kanbanGroup),
      search,
      advancedFilters,
      filterModel,
      sortModel,
    ] as const,
    queryFn: ({ pageParam }): Promise<TableRowsResponse> =>
      fetchTableRows(TASKS_DOMAIN, {
        startRow: pageParam,
        endRow: pageParam + TASK_KANBAN_COLUMN_PAGE_SIZE,
        sortModel,
        filterModel,
        kanbanGroup,
        ...(search !== '' ? { search } : {}),
        ...(Object.keys(advancedFilters).length > 0 ? { advancedFilters } : {}),
      }),
    initialPageParam: 0,
    getNextPageParam: (lastPage, allPages) => {
      const loaded = allPages.reduce((sum, page) => sum + page.items.length, 0)
      return loaded < lastPage.pagination.total ? loaded : undefined
    },
    enabled,
  })
}
