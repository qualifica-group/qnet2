import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskStatusFormMode } from '@/features/task-statuses/types'

/** Metadata-loading state driving what `TaskStatusForm` renders. */
export type TaskStatusFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.taskStatus.permissions`, fetched by the `show` endpoint); create mode
 * fetches the create-context metadata (`GET /meta/task-statuses`) once.
 */
export function useTaskStatusFormMeta(mode: TaskStatusFormMode): TaskStatusFormMetaState {
  const metaQuery = useResourceMeta('task-statuses', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.taskStatus.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
