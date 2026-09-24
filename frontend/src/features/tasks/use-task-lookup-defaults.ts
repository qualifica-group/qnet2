/**
 * Spec 0154 D-8: resolves the default row of task-types/task-priorities/
 * task-importances, so a NEW task's form precompiles them exactly like the
 * server would when the field is omitted. Each catalog carries at most one
 * `meta.is_default: true` row (server-enforced), so a single page covers it
 * — `limit: 100` matches the for-select server cap (`FOR_SELECT_PAGE_SIZE`
 * note in `for-select/api.ts`) rather than the smaller client page size, so
 * a catalog with more rows than the default page still resolves correctly.
 */

import { useQuery } from '@tanstack/react-query'
import { fetchForSelect } from '@/features/for-select/api'

const LOOKUP_DEFAULT_FETCH_LIMIT = 100

interface LookupForSelectMeta {
  is_default?: boolean
}

async function fetchDefaultLookupId(resource: string): Promise<number | null> {
  const response = await fetchForSelect(resource, { limit: LOOKUP_DEFAULT_FETCH_LIMIT })
  const defaultItem = response.items.find(
    (item) => (item as { meta?: LookupForSelectMeta }).meta?.is_default === true,
  )
  return defaultItem?.id ?? null
}

export interface TaskLookupDefaults {
  taskTypeId: number | null
  taskPriorityId: number | null
  taskImportanceId: number | null
}

/** `enabled` gates every query at once: the create form is the only caller (edit derives nothing from this). */
export function useTaskLookupDefaults(enabled: boolean): TaskLookupDefaults {
  const typeQuery = useQuery({
    queryKey: ['task-types', 'default'],
    queryFn: () => fetchDefaultLookupId('task-types'),
    enabled,
  })
  const priorityQuery = useQuery({
    queryKey: ['task-priorities', 'default'],
    queryFn: () => fetchDefaultLookupId('task-priorities'),
    enabled,
  })
  const importanceQuery = useQuery({
    queryKey: ['task-importances', 'default'],
    queryFn: () => fetchDefaultLookupId('task-importances'),
    enabled,
  })

  return {
    taskTypeId: typeQuery.data ?? null,
    taskPriorityId: priorityQuery.data ?? null,
    taskImportanceId: importanceQuery.data ?? null,
  }
}
