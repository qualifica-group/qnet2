import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskImportanceFormMode } from '@/features/task-importances/types'

/** Metadata-loading state driving what `TaskImportanceForm` renders. */
export type TaskImportanceFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.taskImportance.permissions`, fetched by the `show` endpoint); create mode
 * fetches the create-context metadata (`GET /meta/task-importances`) once.
 */
export function useTaskImportanceFormMeta(mode: TaskImportanceFormMode): TaskImportanceFormMetaState {
  const metaQuery = useResourceMeta('task-importances', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.taskImportance.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
