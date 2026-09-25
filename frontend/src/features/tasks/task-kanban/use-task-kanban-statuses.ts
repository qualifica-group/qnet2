import { useQuery } from '@tanstack/react-query'
import { fetchForSelect } from '@/features/for-select/api'
import { TASK_STATUSES_FOR_SELECT_RESOURCE, type TaskStatusForSelectItem } from '@/features/tasks/for-select-api'

/** The whole catalog fits comfortably under the for-select server cap. */
const TASK_STATUS_CATALOG_FETCH_LIMIT = 100

/**
 * The whole `task-statuses` catalog, already in `sort_order` (mirrors
 * `useTaskStatusGroupLookup`'s own fetch shape, but keeps every field the
 * "per stato" Kanban's columns need — id/label/color/group — rather than
 * reducing it to a lookup map).
 */
export function useTaskKanbanStatuses() {
  const query = useQuery({
    queryKey: ['task-statuses', 'kanban-catalog'],
    queryFn: async (): Promise<TaskStatusForSelectItem[]> => {
      const response = await fetchForSelect(TASK_STATUSES_FOR_SELECT_RESOURCE, {
        limit: TASK_STATUS_CATALOG_FETCH_LIMIT,
      })
      return response.items as TaskStatusForSelectItem[]
    },
    staleTime: 5 * 60 * 1000,
  })
  return { statuses: query.data ?? [], isPending: query.isPending, isError: query.isError }
}
