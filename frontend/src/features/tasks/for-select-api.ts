import type { ForSelectItem } from '@/features/for-select/types'
import type { TaskStatusSystemKey } from '@/features/tasks/types'

/** Resource segment of the tasks for-select endpoint (`GET /api/tasks/for-select`). */
export const TASKS_FOR_SELECT_RESOURCE = 'tasks'

/** Resource segments of the five configuration for-select endpoints (D-1). */
export const TASK_STATUSES_FOR_SELECT_RESOURCE = 'task-statuses'
export const TASK_TYPES_FOR_SELECT_RESOURCE = 'task-types'
export const TASK_CATEGORIES_FOR_SELECT_RESOURCE = 'task-categories'
export const TASK_PRIORITIES_FOR_SELECT_RESOURCE = 'task-priorities'
export const TASK_IMPORTANCES_FOR_SELECT_RESOURCE = 'task-importances'

/**
 * The presentation bag `GET /api/task-statuses/for-select` carries on each
 * option, mirroring `TaskStatusForSelectResource::forSelectItem()` key for key
 * (key ORDER there differs and is irrelevant here).
 *
 * The form needs all four: `completion_percentage` (already an `int`
 * server-side) is what makes the read-only percentage update on a status pick
 * without refetching the detail (AC-084); `system_key` is the only thing the
 * D-7 closure-feedback rule may branch on, since a status LABEL never enters a
 * condition (AC-024); `color`/`icon` render the same badge the grid renders.
 *
 * `system_key` is `null` on a CUSTOM status and is NOT stripped from the
 * payload (`ForSelectResource` filters top-level keys only, not inside
 * `meta`): that null IS the "belongs to no phase" signal, so the feedback rule
 * must not fire on it (AC-034/D-5).
 */
export interface TaskStatusForSelectMeta {
  system_key: TaskStatusSystemKey | null
  completion_percentage: number
  color: string
  icon: string | null
}

/** A single task status option as returned by `GET /api/task-statuses/for-select`. */
export interface TaskStatusForSelectItem extends ForSelectItem {
  meta: TaskStatusForSelectMeta
}

/**
 * Reads the status presentation bag off a `ForSelectItem` the generic picker
 * handed back. Tolerant by design: an option carrying no `meta` at all
 * resolves to `null` and the form shows no percentage rather than a wrong one.
 * That branch is not dead code — it also covers the window before the
 * `task-statuses/for-select` route is registered. The server stays the
 * authority on the D-7 rule either way (the 422 handling is never removed).
 *
 * NOTE: `meta.system_key === null` is NOT the same case. It is a real, fully
 * projected custom status, and `isClosingStatus` already resolves it to
 * `false` (AC-034).
 */
export function taskStatusMetaOf(item: ForSelectItem | null): TaskStatusForSelectMeta | null {
  return (item as TaskStatusForSelectItem | null)?.meta ?? null
}
