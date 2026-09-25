/**
 * Task detail/resource types (spec 0101): the shape `GET|POST|PATCH /api/tasks`
 * returns (`TaskResource`). Split out of `types.ts` purely to keep that file
 * under the engineering.md §6 size budget; `types.ts` re-exports everything
 * here so existing callers keep importing from there.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'
import type { TaskRecurrenceDetail } from '@/features/tasks/task-recurrence-types'

/**
 * `App\Enums\TaskStatusSystemKey` (D-5). The ONLY thing application logic may
 * branch on: a status LABEL never enters a condition (AC-024). A custom
 * status created by an admin carries `null` here and is therefore never a
 * closing one. The working phases (`in_progress`/`pending`/`in_validation`)
 * are NOT system keys: they live on the status `group`, which both system and
 * custom rows carry.
 */
export type TaskStatusSystemKey = 'open' | 'closed_positive' | 'closed_negative'

/** Badge projection shared by type/priority/importance/category (`{id,name,color,icon}`). */
export interface TaskLookupRef {
  id: number
  name: string
  /** A `BADGE_COLOR_TOKENS` token (D-4), never a hex. */
  color: string
  /** A curated `ICON_NAMES` lucide name, or `null`. */
  icon: string | null
}

/**
 * The status projection: a lookup ref plus the columns only `task_statuses`
 * has. `group` is the PHASE and is what the closure rule branches on since the
 * 2026-09-04 rectification of D-5; `system_key` survives for protection only
 * (which rows may not be deleted/reordered), no longer for closure.
 */
export interface TaskStatusRef extends TaskLookupRef {
  system_key: TaskStatusSystemKey | null
  group: TaskStatusGroupValue
  completion_percentage: number
}

/** A `{id, name}` projection (registry, referent, opportunity, users). */
export interface TaskNamedRef {
  id: number
  name: string
}

/**
 * The linked Lead projection (spec 0154 D-4): a `{id, label}` shape, NOT
 * `{id, name}` like `TaskNamedRef` — a Lead has no name of its own
 * (`LeadResource`/`LEADS_FOR_SELECT_RESOURCE` already project `label`), so
 * `TaskResource.lead` mirrors that exact shape rather than inventing a `name`.
 */
export interface TaskLeadRef {
  id: number
  label: string
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

/** The linked "Fase" (spec 0146 D-2/D-3) projection: a plain `{id, name}`, no color/order of its own here. */
export interface TaskWorkOrderStageRef {
  id: number
  name: string
}

/**
 * One child of the task, as exposed by `TaskResource.subtasks` (D-12): a lean
 * array already filtered by the visibility scope (AC-066). NO endpoint of its
 * own for LISTING — the Sotto-task section reads this off the detail already
 * loaded; reordering, completing, reopening and deleting a child each reuse
 * the task's own generic endpoints (`reorderTaskSubtasks`/`completeTask`/
 * `uncompleteTask`/`deleteTask`) against the child's own `id`.
 *
 * Spec 0155 D-4/D-5 adds `position` (the `subtask_position` column, what the
 * reorder endpoint writes) and `permissions.actions` — the SAME shape as the
 * parent's own `TaskDetailWithPermissions.permissions.actions` (D-5): the
 * panel gates complete/reopen per row from this, never from the parent's own
 * flags.
 */
export interface TaskSubtask {
  id: number
  title: string
  task_status: TaskLookupRef
  completion_percentage: number
  assignees: TaskNamedRef[]
  position: number
  /** The child's own action matrix; `delete` is its own delete verdict (spec 0153 D-5). */
  permissions: { actions: Record<TaskActionKey | 'delete', boolean> }
}

/**
 * Single task detail returned by GET/POST/PATCH /tasks (envelope `data`).
 * Matches `TaskResource`.
 */
export interface TaskDetail {
  id: number
  title: string
  description: string | null
  /**
   * Spec 0154 D-2: when `true`, only the creator, requester, assignees and
   * watchers see this task — `tasks.viewAll` and the by-site visibility do
   * not (the super-admin `Gate::before` stays the one exception). Sanitized
   * HTML, same treatment as `description`.
   */
  is_private: boolean
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
  /** Spec 0154 D-4: must belong to `registry_id` when the task has one (422 otherwise). */
  lead_id: number | null
  lead: TaskLeadRef | null
  /**
   * The "Fase" of the linked commessa this ROOT task sits in (spec 0146
   * D-2/D-3), `null` for "Senza fase" or when the task carries no commessa or
   * has a parent — a sub-task's own `work_order_stage_id` is `prohibited`
   * server-side (D-3), so this is meaningless on anything but a root task.
   */
  work_order_stage_id: number | null
  work_order_stage: TaskWorkOrderStageRef | null
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
  /**
   * The series this task belongs to (spec 0120 D-3/D-4), or `null` when it
   * carries no repetition rule. The capostipite IS the first occurrence: it
   * holds this same object, not a separate "template".
   */
  recurrence: TaskRecurrenceDetail | null
  /**
   * "Bloccato/contestato" — a flag DISTINCT from the status (AC-086).
   * Read-only here (spec 0116 D-6): written ONLY by `blockTask`/`unblockTask`,
   * never part of `CreateTaskPayload`/`UpdateTaskPayload`.
   */
  is_blocked: boolean
  requires_closure_feedback: boolean
  /**
   * Spec 0162 D-1/D-2: derived from `task_type.requires_time_entry` (`true`
   * when the task carries no tipologia). Read-only, drives whether the
   * "Completa" dialog's segnatempo section is mandatory or shows the
   * "Registra il tempo" switch — the FE never decides the requirement
   * itself, only relays this flag.
   */
  requires_time_entry: boolean
  /**
   * Whether an assignee's completion goes to validation instead of closing
   * the task outright (spec 0121 D-1/D-2): the server alone decides SO from
   * this flag and the actor's mandate — see `permissions.actions.complete_to_validation`.
   */
  requires_validation: boolean
  closure_feedback: string | null
  /**
   * DERIVED from `task_status.completion_percentage` at response time (D-6):
   * `tasks` has no such column, so this is read-only and never part of a
   * payload (AC-084).
   */
  completion_percentage: number
  /**
   * Direct children in a phase other than `closed_positive`/`closed_negative`
   * (spec 0123 D-6), counted server-side IGNORING visibility (AC-021): the UI
   * reads this to explain why `/complete`/`approve` are unavailable
   * (`permissions.actions.complete`/`complete_to_validation`/`approve` are
   * false whenever this is `> 0`), never to gate the action itself.
   */
  open_subtasks_count: number
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
 * The seven domain action keys `TasksAuthorization::actions()` adds to
 * `permissions.actions` (spec 0116 data_contract, spec 0118 D-10): complete/reopen
 * a task, approve/reject its validation, block/unblock it, and ask its people for
 * an update. Each flag on `ResourcePermissions.actions` is already the AND of
 * ability, record-role matrix and state availability (D-1) — the frontend never
 * recomputes it, only reads it (see `task-action-availability.ts` for the UX-only
 * mirror of the state half).
 *
 * `request_update` (RECTIFIED by spec 0153 D-14) is granted to the requester,
 * the creator or a manager — never a pure OSSERVATORE — and only on a task that
 * is neither completed, in validation, nor blocked.
 *
 * `complete_to_validation` (spec 0121 D-6) stays a SIBLING flag on the same
 * `permissions.actions` object, not a member of this union: it never gates a
 * button's presence, it only tells the already-visible "Completa" dialog
 * which variant to render, so it has no matching entry in
 * `TaskActionAvailabilityFlags`.
 *
 * `close_via_status` and `create_subtask` (spec 0123 D-5/D-9) join the union
 * for the SAME reason `complete_to_validation` stays out of it: they gate,
 * respectively, individual options of the Stato select and the "Crea
 * sotto-task" button — never a phase-based mirror in
 * `task-action-availability.ts`, which is why that file EXCLUDES them from
 * `TaskActionAvailabilityFlags` rather than growing two more entries there.
 *
 * `change_status` (spec 0126 D-4) joins for the same reason: it gates the
 * Stato select AS A WHOLE (disabled entirely when false), not a phase-based
 * button, so `task-action-availability.ts` excludes it too.
 */
export type TaskActionKey =
  | 'complete'
  | 'uncomplete'
  | 'approve'
  | 'reject'
  | 'block'
  | 'unblock'
  | 'request_update'
  | 'close_via_status'
  | 'create_subtask'
  | 'change_status'
