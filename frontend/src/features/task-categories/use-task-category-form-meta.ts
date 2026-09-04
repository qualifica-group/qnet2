import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskCategoryFormMode } from '@/features/task-categories/types'

/** Metadata-loading state driving what `TaskCategoryForm` renders. */
export type TaskCategoryFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.taskCategory.permissions`, fetched by the `show` endpoint); create mode
 * fetches the create-context metadata (`GET /meta/task-categories`) once.
 */
export function useTaskCategoryFormMeta(mode: TaskCategoryFormMode): TaskCategoryFormMetaState {
  const metaQuery = useResourceMeta('task-categories', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.taskCategory.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
