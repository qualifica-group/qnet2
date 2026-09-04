import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskPriorityFormMode } from '@/features/task-priorities/types'

/** Metadata-loading state driving what `TaskPriorityForm` renders. */
export type TaskPriorityFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.taskPriority.permissions`, fetched by the `show` endpoint); create mode
 * fetches the create-context metadata (`GET /meta/task-priorities`) once.
 */
export function useTaskPriorityFormMeta(mode: TaskPriorityFormMode): TaskPriorityFormMetaState {
  const metaQuery = useResourceMeta('task-priorities', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.taskPriority.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
