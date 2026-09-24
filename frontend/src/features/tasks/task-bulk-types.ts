/**
 * Bulk-action types for `POST /api/tasks/bulk` (spec 0156 D-6). Kept apart
 * from `types.ts` purely to stay under the engineering.md §6 size budget —
 * `types.ts` already sits at its own limit.
 */
import type { CompleteTaskTimeEntryPayload } from '@/features/tasks/types'

/** The eight bulk actions of spec 0156 D-6, matching `POST /api/tasks/bulk`'s `action` enum. */
export type TaskBulkAction =
  | 'assign'
  | 'complete'
  | 'uncomplete'
  | 'block'
  | 'unblock'
  | 'priority'
  | 'start_date'
  | 'end_date'
  | 'delete'

/**
 * Body of `POST /api/tasks/bulk` (spec 0156 contract): every field but
 * `action`/`task_ids` is specific to one action, so a caller only ever
 * populates the ones its own action needs. `for_all_assignees` is never sent
 * (the server always applies it, D-6).
 */
export interface TaskBulkPayload {
  action: TaskBulkAction
  task_ids: number[]
  /** `assign` only: REPLACES the assignee set of every selected task. */
  assignee_ids?: number[]
  /** `priority` only. */
  task_priority_id?: number
  /** `start_date`/`end_date` only, `Y-m-d`. */
  date?: string
  /** `complete` only, same shape `POST /tasks/{id}/complete` accepts. */
  time_entry?: CompleteTaskTimeEntryPayload
  /** `complete` only, optional. */
  closure_feedback?: string
  /** `complete` only, optional: the `in_validation` destination for tasks that require validation. */
  validation_status_id?: number
}

/** One task the bulk endpoint refused (spec 0156 contract): `reason` is already the server's own i18n message. */
export interface TaskBulkIncompatibleTask {
  id: number
  reason: string
}

/** Success body of `POST /api/tasks/bulk` (envelope `data`). */
export interface TaskBulkResult {
  affected: number
}
