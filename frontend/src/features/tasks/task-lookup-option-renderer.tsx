import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import type { ForSelectItem } from '@/features/for-select/types'

/**
 * `renderItem` for an `AsyncPaginatedSelect` over a task lookup resource
 * (task-types/-priorities/-importances/-statuses): projects the for-select
 * item's own `meta.color`/`meta.icon` onto `TaskLookupBadge`, so every picker
 * over these four catalogs renders identically without repeating the mapping
 * at each call site (spec 0156 D-6/D-7: the bulk priority dialog and the
 * quick-create row both need it).
 */
export function taskLookupOptionRenderer(item: ForSelectItem) {
  const meta = (item as ForSelectItem & { meta?: { color?: string; icon?: string | null } }).meta
  return <TaskLookupBadge value={{ id: item.id, name: item.label, color: meta?.color ?? '', icon: meta?.icon ?? null }} />
}
