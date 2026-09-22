/**
 * Task board CRUD/read types (spec 0146). The generic table types (columns/
 * filters/actions/rows) do NOT apply here — the board replaces `TableView`
 * entirely (D-10, AC-024). Source of truth: the frozen `data_contract`
 * (`WorkOrderStage`, `BoardTask`) and its five write endpoints.
 */

import type { ApiErrorResponse } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { CompleteTaskTimeEntryPayload, TaskLookupRef, TaskNamedRef, TaskStatusRef } from '@/features/tasks/types'

/** A phase of the commessa (`work_order_stages`), Italian "Fase" (D-2/D-4). */
export interface WorkOrderStage {
  id: number
  name: string
  sort_order: number
  /** ISO datetime, set by `POST .../stages/{stage}/close`; `null` while open. */
  closed_at: string | null
  closed_by: { id: number; name: string } | null
}

/**
 * One row of the board's flat task list (roots AND sub-tasks, D-3): a
 * projection of `TaskResource` trimmed to what the board renders, plus the
 * same `permissions` block `GET /api/tasks/{id}` carries (D-9) — the board
 * hides an action exactly where the server would refuse it, never
 * recomputing authorization on its own.
 *
 * Only a ROOT row (`parent_task_id === null`) has a meaningful
 * `work_order_stage_id`/`stage_position` (D-3): a sub-task always carries
 * `work_order_stage_id: null` and a `stage_position` that no grouping
 * function reads.
 */
export interface BoardTask {
  id: number
  title: string
  /** Plain-text description, cut at 160 characters by the server; `null` when empty. */
  description_excerpt: string | null
  parent_task_id: number | null
  work_order_stage_id: number | null
  stage_position: number
  task_status: TaskStatusRef
  task_type: TaskLookupRef | null
  task_priority: TaskLookupRef | null
  task_importance: TaskLookupRef | null
  requester: TaskNamedRef | null
  assignees: TaskNamedRef[]
  watchers: TaskNamedRef[]
  /** `Y-m-d` */
  start_date: string | null
  end_date: string | null
  estimated_minutes: number | null
  /** Sum of this task's own `time_entries.minutes` (AC-011), never the children's. */
  actual_minutes: number
  is_blocked: boolean
  attachments_count: number
  permissions: ResourcePermissions
}

/** Response shape of `GET /work-orders/{workOrder}/task-board` (envelope `data`). */
export interface TaskBoardPayload {
  /** In `sort_order`. */
  stages: WorkOrderStage[]
  /** Flat, roots ordered by (fase, `stage_position`); sub-tasks interleaved (AC-011). */
  tasks: BoardTask[]
  /** Closed commessa, or the actor lacks `update` on it (D-9): no drag, no fase/bulk actions. */
  is_read_only: boolean
}

/**
 * Payload for `POST /work-orders/{workOrder}/task-board/move`. `position` is
 * the destination index INSIDE the target group (fase or "Senza fase"),
 * counted AFTER the task leaves its origin group (AC-013).
 */
export interface MoveBoardTaskPayload {
  task_id: number
  work_order_stage_id: number | null
  position: number
}

/** One repositioned row of the move response — either the origin or the destination group, compact `0..n-1`. */
export interface MoveBoardTaskResultRow {
  id: number
  work_order_stage_id: number | null
  stage_position: number
}

/** Response shape of the move endpoint (envelope `data`): only the rows that actually moved. */
export interface MoveBoardTaskResult {
  tasks: MoveBoardTaskResultRow[]
}

/** Bulk "assegna" (D-7): replaces the assignee set of every selected task. */
export interface BulkAssignPayload {
  action: 'assign'
  task_ids: number[]
  assignee_ids: number[]
}

/**
 * Bulk "completa": ONE `closure_feedback`/`time_entry` applied to every
 * selected task (D-7). A task requiring validation fails that row instead of
 * completing it — validation stays a single-task action.
 */
export interface BulkCompletePayload {
  action: 'complete'
  task_ids: number[]
  closure_feedback?: string | null
  time_entry: CompleteTaskTimeEntryPayload
}

/** Bulk "riapri". */
export interface BulkUncompletePayload {
  action: 'uncomplete'
  task_ids: number[]
}

/**
 * Bulk "blocca". `BlockTaskRequest` (the single-task endpoint this mirrors)
 * carries no body of its own, so this action does too — only the selection.
 */
export interface BulkBlockPayload {
  action: 'block'
  task_ids: number[]
}

/** Bulk "cambia priorita'". */
export interface BulkPriorityPayload {
  action: 'priority'
  task_ids: number[]
  task_priority_id: number
}

/** Bulk "cambia date": at least one of the two, `end_date >= start_date` (server-checked, 422 otherwise). */
export interface BulkDatesPayload {
  action: 'dates'
  task_ids: number[]
  start_date?: string | null
  end_date?: string | null
}

/** Discriminated request body of `POST /work-orders/{workOrder}/task-board/bulk` (D-7), one variant per action. */
export type BulkTaskPayload =
  | BulkAssignPayload
  | BulkCompletePayload
  | BulkUncompletePayload
  | BulkBlockPayload
  | BulkPriorityPayload
  | BulkDatesPayload

/** The six selectable bulk actions, derived from the payload union so the two never drift apart. */
export type BulkTaskAction = BulkTaskPayload['action']

/** One task's own outcome inside a bulk response: a failure never blocks the others (D-7). */
export interface BulkTaskActionResult {
  task_id: number
  ok: boolean
  /** i18n reason of a `false` outcome (403/409/domain rule); `null` on success. */
  message: string | null
}

/** Response shape of the bulk endpoint (envelope `data`). */
export interface BulkTaskResult {
  results: BulkTaskActionResult[]
  succeeded: number
  failed: number
}

/**
 * Error envelope of `POST .../stages/{stage}/close` on 409 (D-4/AC-008): the
 * `open_tasks_count` sibling of `errors` the confirm/toast needs to name the
 * blocking count. `BaseApiController::fail()` has no `data` slot on error
 * responses today (backend.md), so this extends the generic shape locally
 * rather than widening the shared `ApiErrorResponse` for one endpoint.
 */
export interface CloseStageConflictError extends ApiErrorResponse {
  data?: { open_tasks_count: number }
}

/** The two board layouts toggled by D-5, persisted in `localStorage` (see `use-task-board-view-mode.ts`). */
export type TaskBoardViewMode = 'list' | 'kanban'

/** `end_date ?? start_date` due filter (D-6). */
export type TaskBoardDueFilter = 'all' | 'today' | 'overdue' | 'this_week'

/** Status filter (D-6): "Aperti" is the default. */
export type TaskBoardStatusFilter = 'open' | 'completed' | 'blocked' | 'all'

/** Assignment filter (D-6): "a me" reads `assignees`, "da me" reads `requester`. */
export type TaskBoardAssignmentFilter = 'all' | 'assigned_to_me' | 'requested_by_me'

/** Client-side filter state (D-6), applied to ROOT tasks only by `filterBoardTasks`. */
export interface TaskBoardFilters {
  search: string
  taskTypeIds: number[]
  due: TaskBoardDueFilter
  status: TaskBoardStatusFilter
  assignment: TaskBoardAssignmentFilter
  requesterIds: number[]
  assigneeIds: number[]
  watcherIds: number[]
  taskPriorityIds: number[]
  taskStatusIds: number[]
  taskImportanceIds: number[]
}
