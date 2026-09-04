import type { ForSelectItem } from '@/features/for-select/types'
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'
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
 * The form reads four of them: `completion_percentage` (already an `int`
 * server-side) is what makes the read-only percentage update on a status pick
 * without refetching the detail (AC-084); `system_key` is the only thing the
 * D-7 closure-feedback rule may branch on, since a status LABEL never enters a
 * condition (AC-024); `color`/`icon` render the same badge the grid renders.
 * `group` is declared because the endpoint projects it, not because this form
 * reads it: the phase drives the status configurator, and leaving it out here
 * would make this type drift from the response it claims to mirror.
 *
 * `system_key` is `null` on a CUSTOM status and is NOT stripped from the
 * payload (`ForSelectResource` filters top-level keys only, not inside
 * `meta`): that null IS the "is not a system row" signal, so the feedback rule
 * must not fire on it (AC-034/D-5). It is NOT a missing phase — `group` is
 * always present, on system and custom rows alike.
 */
export interface TaskStatusForSelectMeta {
  system_key: TaskStatusSystemKey | null
  group: TaskStatusGroupValue
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
 * NOTE: `meta.system_key === null` is NOT the same case, and since the
 * 2026-09-04 rectification it decides nothing at all: it is a real, fully
 * projected ORDINARY status, whose `group` is present like any other and may
 * well be a closing phase. The rule reads that phase, so such a status DOES
 * require the feedback — the opposite of what AC-034 stated when closing was
 * keyed off `system_key`.
 */
export function taskStatusMetaOf(item: ForSelectItem | null): TaskStatusForSelectMeta | null {
  return (item as TaskStatusForSelectItem | null)?.meta ?? null
}
