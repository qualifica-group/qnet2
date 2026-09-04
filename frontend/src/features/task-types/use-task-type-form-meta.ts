import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskTypeFormMode } from '@/features/task-types/types'

/** Metadata-loading state driving what `TaskTypeForm` renders. */
export type TaskTypeFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.taskType.permissions`, fetched by the `show` endpoint); create mode
 * fetches the create-context metadata (`GET /meta/task-types`) once.
 */
export function useTaskTypeFormMeta(mode: TaskTypeFormMode): TaskTypeFormMetaState {
  const metaQuery = useResourceMeta('task-types', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.taskType.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
