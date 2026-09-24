import { useQuery } from '@tanstack/react-query'
import { fetchForSelect } from '@/features/for-select/api'
import { TASK_STATUSES_FOR_SELECT_RESOURCE, type TaskStatusForSelectItem } from '@/features/tasks/for-select-api'
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'

const TASK_STATUS_LOOKUP_FETCH_LIMIT = 100

async function fetchTaskStatusGroups(): Promise<Map<number, TaskStatusGroupValue>> {
  const response = await fetchForSelect(TASK_STATUSES_FOR_SELECT_RESOURCE, { limit: TASK_STATUS_LOOKUP_FETCH_LIMIT })
  return new Map(response.items.map((item) => [item.id, (item as TaskStatusForSelectItem).meta.group]))
}

/**
 * The whole `task-statuses` catalog reduced to `id -> group` (spec 0156 D-8):
 * the cell interceptor needs the picked status's phase to decide whether a
 * `task_status` cell commit is a closure/reopening, and the row's own cell
 * value carries no group for the option the user is ABOUT to pick — only for
 * the one already there. Cached across the app (the catalog rarely changes),
 * mirrors `useTaskLookupDefaults`'s own for-select fetch shape.
 */
export function useTaskStatusGroupLookup() {
  const query = useQuery({
    queryKey: ['task-statuses', 'group-lookup'],
    queryFn: fetchTaskStatusGroups,
    staleTime: 5 * 60 * 1000,
  })
  return query.data ?? new Map<number, TaskStatusGroupValue>()
}
