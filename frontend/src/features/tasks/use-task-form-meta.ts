import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { TASKS_DOMAIN } from '@/features/tasks/api'
import type { ResourcePermissions } from '@/features/authorization/types'

/** Metadata-loading state driving what `TaskForm` renders. */
export type TaskFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the create-context `ResourcePermissions` backing the form (spec
 * 0004, `GET /meta/tasks`) once. Duplicate submits through the CREATE
 * endpoint too (spec 0156 D-4), so it resolves the same metadata — never the
 * source task's own `permissions` (the actor's CREATE mandate may differ from
 * their UPDATE mandate on that record). The detail, which edits in place,
 * reads the task's own `permissions` instead (spec 0195).
 */
export function useTaskFormMeta(): TaskFormMetaState {
  const metaQuery = useResourceMeta(TASKS_DOMAIN)

  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
