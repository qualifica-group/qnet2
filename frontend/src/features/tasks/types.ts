/**
 * Task module CRUD types (spec 0101). The generic table types (columns/
 * filters/actions/rows) live in `features/table/types.ts`; this file holds
 * only what is genuinely task-specific. Source of truth: the frozen
 * `data_contract` of `GET|POST|PATCH /api/tasks` (`TaskResource`).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * `App\Enums\TaskStatusSystemKey` (D-5). The ONLY thing application logic may
 * branch on: a status LABEL never enters a condition (AC-024). A custom
 * status created by an admin carries `null` here and therefore belongs to no
 * phase.
 */
export type TaskStatusSystemKey =
  | 'open'
  | 'in_progress'
  | 'pending'
  | 'in_validation'
  | 'closed_positive'
  | 'closed_negative'

/** Badge projection shared by type/priority/importance/category (`{id,name,color,icon}`). */
export interface TaskLookupRef {
  id: number
  name: string
  /** A `BADGE_COLOR_TOKENS` token (D-4), never a hex. */
  color: string
  /** A curated `ICON_NAMES` lucide name, or `null`. */
  icon: string | null
}

/** The status projection: a lookup ref plus the two columns only `task_statuses` has (D-4). */
export interface TaskStatusRef extends TaskLookupRef {
  system_key: TaskStatusSystemKey | null
  completion_percentage: number
}

/** A `{id, name}` projection (registry, referent, opportunity, users). */
export interface TaskNamedRef {
  id: number
  name: string
}

/** The parent task projection: a task is identified by its `title`, not a `name` (D-12). */
export interface TaskParentRef {
  id: number
  title: string
}

/** The linked work order ("Commessa", spec 0093) projection. */
export interface TaskWorkOrderRef {
  id: number
  code: string
  title: string
}

/**
 * One child of the task, as exposed by `TaskResource.subtasks` (D-12): a lean
 * array already filtered by the visibility scope (AC-066). NO endpoint of its
 * own — the Sotto-task section reads this off the detail already loaded.
 */
export interface TaskSubtask {
  id: number
  title: string
  task_status: TaskLookupRef
  completion_percentage: number
  assignees: TaskNamedRef[]
}

/**
 * Single task detail returned by GET/POST/PATCH /tasks (envelope `data`).
 * Matches `TaskResource`.
 */
export interface TaskDetail {
  id: number
  title: string
  description: string | null
  registry_id: number | null
  registry: TaskNamedRef | null
  referent_id: number | null
  referent: TaskNamedRef | null
  parent_task_id: number | null
  parent_task: TaskParentRef | null
  task_type_id: number | null
  task_type: TaskLookupRef | null
  task_status_id: number
  task_status: TaskStatusRef
  task_priority_id: number | null
  task_priority: TaskLookupRef | null
  task_importance_id: number | null
  task_importance: TaskLookupRef | null
  task_category_id: number | null
  task_category: TaskLookupRef | null
  opportunity_id: number | null
  opportunity: TaskNamedRef | null
  work_order_id: number | null
  work_order: TaskWorkOrderRef | null
  requester_id: number | null
  requester: TaskNamedRef | null
  /** Server-side, immutable, never client-writable (D-10): no `creator_id` key exists here. */
  creator: TaskNamedRef
  assignees: TaskNamedRef[]
  watchers: TaskNamedRef[]
  /** `Y-m-d` */
  start_date: string | null
  end_date: string | null
  completion_date: string | null
  /** `H:i`, a column of its own — never fused with the date (D-11). */
  start_time: string | null
  end_time: string | null
  estimated_minutes: number | null
  /** "Bloccato/contestato" — a flag DISTINCT from the status (AC-086). */
  is_blocked: boolean
  requires_closure_feedback: boolean
  closure_feedback: string | null
  /**
   * DERIVED from `task_status.completion_percentage` at response time (D-6):
   * `tasks` has no such column, so this is read-only and never part of a
   * payload (AC-084).
   */
  completion_percentage: number
  subtasks: TaskSubtask[]
  created_at: string
  updated_at: string
}

/**
 * A `TaskDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /tasks/{id}` (`show`). Seeds the
 * edit form's `ResourcePermissionsProvider` without a second request.
 */
export interface TaskDetailWithPermissions extends TaskDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /tasks (create). `creator_id` and `completion_percentage`
 * are `prohibited` server-side (D-6/D-10) and are therefore NOT keys of this
 * type: a caller cannot include them even by accident.
 */
export interface CreateTaskPayload {
  title: string
  task_status_id: number
  description?: string | null
  registry_id?: number | null
  referent_id?: number | null
  parent_task_id?: number | null
  task_type_id?: number | null
  task_priority_id?: number | null
  task_importance_id?: number | null
  task_category_id?: number | null
  opportunity_id?: number | null
  work_order_id?: number | null
  requester_id?: number | null
  start_date?: string | null
  end_date?: string | null
  completion_date?: string | null
  start_time?: string | null
  end_time?: string | null
  estimated_minutes?: number | null
  is_blocked?: boolean
  requires_closure_feedback?: boolean
  closure_feedback?: string | null
  /** Flat id arrays (AC-083); the same user may appear in both. */
  assignee_ids?: number[]
  watcher_ids?: number[]
}

/**
 * Payload for PATCH /tasks/{id} (partial update). The two user arrays are
 * synced server-side ONLY when their key is present, so an untouched
 * selection must not be resent (AC-012).
 */
export type UpdateTaskPayload = Partial<CreateTaskPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and
 * `TaskForm`. `parentTaskId` carries the "crea sotto-task" prefill (AC-085):
 * present means the parent picker renders prefilled AND locked.
 */
export type TaskFormMode =
  | { type: 'create'; parentTaskId?: number | null }
  | { type: 'edit'; task: TaskDetailWithPermissions }
