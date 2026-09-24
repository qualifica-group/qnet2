import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { TASKS_DOMAIN } from '@/features/tasks/api'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskFormMode } from '@/features/tasks/types'

/** Metadata-loading state driving what `TaskForm` renders. */
export type TaskFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail (`mode.task.permissions`,
 * fetched by the `show` endpoint); create mode fetches the create-context
 * metadata (`GET /meta/tasks`) once.
 */
export function useTaskFormMeta(mode: TaskFormMode): TaskFormMetaState {
  // Duplicate submits through the CREATE endpoint (spec 0156 D-4), so it
  // resolves the same create-context metadata as a bare create — never the
  // source task's own `permissions` (the actor's CREATE mandate may differ
  // from their UPDATE mandate on that source record).
  const metaQuery = useResourceMeta(TASKS_DOMAIN, mode.type !== 'edit')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.task.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
