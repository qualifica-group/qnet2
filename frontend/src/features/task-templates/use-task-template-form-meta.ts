import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskTemplateFormMode } from '@/features/task-templates/types'

/** Metadata-loading state driving what `TaskTemplateForm` renders. */
export type TaskTemplateFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.taskTemplate.permissions`, fetched by the `show` endpoint); create
 * mode fetches the create-context metadata (`GET /meta/task-templates`) once.
 */
export function useTaskTemplateFormMeta(mode: TaskTemplateFormMode): TaskTemplateFormMetaState {
  const metaQuery = useResourceMeta('task-templates', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.taskTemplate.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
