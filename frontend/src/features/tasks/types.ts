/**
 * Task module CRUD types (spec 0101). The generic table types (columns/
 * filters/actions/rows) live in `features/table/types.ts`; this file holds
 * only what is genuinely task-specific. Source of truth: the frozen
 * `data_contract` of `GET|POST|PATCH /api/tasks` (`TaskResource`).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'
import type { CreateTaskSubtaskPayload } from '@/features/tasks/task-subtask-types'

export type { CreateTaskSubtaskPayload } from '@/features/tasks/task-subtask-types'

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

/** The `recurrence` slice of the contract (spec 0120) lives in its own file; re-exported here so existing callers keep importing from `types.ts`. */
export {
  TASK_RECURRENCE_END_MODES,
  TASK_RECURRENCE_FREQUENCIES,
  TASK_RECURRENCE_MONTH_MODES,
  type TaskRecurrenceDetail,
  type TaskRecurrenceEndMode,
  type TaskRecurrenceFrequency,
  type TaskRecurrenceMonthMode,
  type TaskRecurrencePayload,
} from '@/features/tasks/task-recurrence-types'
import type { TaskRecurrenceDetail, TaskRecurrencePayload } from '@/features/tasks/task-recurrence-types'

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
  /** Spec 0154 D-3: free rich text, nullable, sanitized like `description`. */
  evidence: string | null
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

/**
 * `time_entry` payload nested in `CompleteTaskPayload` (spec 0123 D-1/D-3):
 * the segnatempo `/complete` creates in the SAME transaction as the status
 * change. Structurally the same shape `POST /tasks/{task}/time-entries`
 * accepts (`CreateTaskTimeEntryPayload`, `time-entries/types.ts`), declared
 * again here rather than imported: it belongs to the Task endpoint's OWN
 * contract (D-3's "one source of truth" is about the VALIDATION rules,
 * enforced server-side from a shared rule set — not about this wire type).
 */
export interface CompleteTaskTimeEntryPayload {
  date: string
  task_type_id: number
  minutes: number
  start_time?: string | null
  end_time?: string | null
  notes?: string | null
}

/**
 * Payload for POST /tasks/{id}/complete (spec 0121 data_contract, RECTIFIES
 * spec 0116 D-4: the client no longer chooses the path). The server alone
 * decides whether the completion closes the task or sends it to validation
 * (`TaskAbilityResolver::completionRequiresValidation`, mirrored by
 * `permissions.actions.complete_to_validation`); `validation_status_id` is
 * mandatory on that path and forbidden on the other one (D-3) — the dialog
 * builds this payload accordingly, never from a client-side switch.
 *
 * `time_entry` is REQUIRED on both paths (spec 0123 D-1) UNLESS the task's
 * own `requires_time_entry` (spec 0162 D-2/D-3) is `false`: the dialog then
 * shows a "Registra il tempo" switch and omits this key when it is off.
 *
 * `for_all_assignees` (spec 0155 D-6): omitted (server default `false`)
 * completes for the acting user alone; `true` logs an identical time entry
 * for every assignee (the actor alone when there is none). The dialog sends
 * it from a `forAllAssignees` PROP, never a user-facing toggle (q-net has
 * none): `true` from the task detail/list, `false` from the sub-task panel.
 */
export interface CompleteTaskPayload {
  closure_feedback?: string | null
  validation_status_id?: number | null
  time_entry?: CompleteTaskTimeEntryPayload
  for_all_assignees?: boolean
}

/**
 * Payload for POST /tasks (create). `creator_id` and `completion_percentage`
 * are `prohibited` server-side (D-6/D-10) and are therefore NOT keys of this
 * type: a caller cannot include them even by accident. `is_blocked` left the
 * catalog entirely (spec 0116 D-6): it is written ONLY by `/block`/`/unblock`,
 * never by a PATCH, so it is not a key here either.
 *
 * `task_status_id` LEFT THIS TYPE in spec 0118 (D-3) and RE-JOINED it in spec
 * 0154 (D-10): a manually picked initial status is now allowed on create
 * (`sometimes`), still derived server-side (D-4 of 0118) when omitted; a
 * closing/in-validation/action-only status 422s either way.
 *
 * `requester_id`, `assignee_ids` and `end_date` are REQUIRED here, not optional
 * (spec 0118 D-1): the document's four mandatory creation fields, with `title`.
 */
export interface CreateTaskPayload {
  title: string
  requester_id: number
  assignee_ids: number[]
  end_date: string
  description?: string | null
  /** Spec 0154 D-2. */
  is_private?: boolean
  /** Spec 0154 D-3. */
  evidence?: string | null
  registry_id?: number | null
  referent_id?: number | null
  parent_task_id?: number | null
  task_type_id?: number | null
  task_priority_id?: number | null
  task_importance_id?: number | null
  task_category_id?: number | null
  opportunity_id?: number | null
  work_order_id?: number | null
  /**
   * Spec 0146 D-2/D-3: must belong to the `work_order_id` sent or persisted,
   * `prohibited` on a task carrying a `parent_task_id` (422 either way). The
   * form only ever sends it on a ROOT task with a commessa selected.
   */
  work_order_stage_id?: number | null
  /** Spec 0154 D-4: must belong to `registry_id` when set (422 otherwise). */
  lead_id?: number | null
  start_date?: string | null
  start_time?: string | null
  end_time?: string | null
  estimated_minutes?: number | null
  requires_closure_feedback?: boolean
  /**
   * Spec 0121 D-1: PROTECTED like `requires_closure_feedback` — a PATCH from
   * an actor without the mandate 422s on this field (see `UpdateTaskPayload`).
   */
  requires_validation?: boolean
  /**
   * Spec 0154 D-10: a manually picked initial status. `sometimes`: a
   * closing/in-validation/action-only row 422s on this field; omitted, the
   * server derives it as before (0118 D-4).
   */
  task_status_id?: number
  /**
   * Spec 0154 D-6: the task is born already closed positively, with a time
   * entry logging the actor's `estimated_minutes` (even 0), bypassing
   * validation. 422 when `requires_validation`/`requires_closure_feedback`
   * would otherwise demand a feedback this path never collects.
   */
  is_completed?: boolean
  /**
   * Spec 0154 D-7: `false` suppresses the assignment/watch notifications this
   * create would otherwise send. Create-only — PATCH uses
   * `notify_new_assigned_users` instead (see `UpdateTaskPayload`). Omitted,
   * the server default (`true`) applies.
   */
  notify_assigned_users?: boolean
  /**
   * Spec 0120 D-12: PROTECTED, same mandate as the flag above. `null` clears
   * the series (PATCH only, D-10); the key is entirely absent when the actor
   * may not touch it (`task-form-payload.ts`), never sent as a no-op.
   */
  recurrence?: TaskRecurrencePayload | null
  /**
   * Flat id arrays. Since spec 0118 D-9 the two sets are DISJOINT: an id in
   * `watcher_ids` may be neither the creator, nor the requester, nor an assignee
   * (422 field-scoped). This retires AC-083 of spec 0101, which allowed the
   * overlap — the requirement changed by user decision.
   */
  watcher_ids?: number[]
  /**
   * Spec 0155 D-3: create-only bulk sub-tasks, one level, max 50 rows, all
   * created in the SAME transaction as the parent (one invalid row 422s as
   * `subtasks.N.field` and rolls back the whole create). Every field but
   * `title` inherits from the parent when omitted; never phase or
   * recurrence — a sub-task carries neither. Omitted or empty, no key at all
   * (`task-form-payload.ts`).
   */
  subtasks?: CreateTaskSubtaskPayload[]
}

/**
 * Payload for PATCH /tasks/{id} (partial update). The two user arrays are
 * synced server-side ONLY when their key is present, so an untouched
 * selection must not be resent (AC-012).
 *
 * `task_status_id` is added back on purpose (spec 0118 D-3): the status is
 * derived at creation and never re-derived afterwards (D-6), so PATCH — the
 * detail's own picker and nothing else — is the one place a client may write it.
 *
 * `is_completed` and `notify_assigned_users` are OMITTED from the base type
 * here (spec 0154 D-6/D-7): both are create-only concepts with no persisted
 * counterpart to re-send on a PATCH. `notify_new_assigned_users` is the
 * edit-mode sibling of `notify_assigned_users`, added back explicitly.
 */
export type UpdateTaskPayload = Partial<Omit<CreateTaskPayload, 'is_completed' | 'notify_assigned_users'>> & {
  task_status_id?: number
  /** Spec 0154 D-7: `false` suppresses notifications for the NEW assignees/watchers this PATCH adds. */
  notify_new_assigned_users?: boolean
}

/** The three fixed recipient groups for `RequestTaskUpdatePayload.target` (spec 0153 D-14). */
export type TaskRequestUpdateTarget = 'assignees' | 'observers' | 'all'

/**
 * Payload for POST /tasks/{id}/request-update (spec 0153 D-14, RECTIFIES
 * spec 0118's free `recipient_ids` picker). `target` selects a fixed group —
 * `assignees` notifies every assignee and CCs every watcher on a separate
 * `is_cc: true` notification, `observers` notifies every watcher, `all`
 * notifies both groups outright; never an arbitrary id list. `message` is
 * now mandatory (3..2000 chars), validated the same client- and server-side.
 */
export interface RequestTaskUpdatePayload {
  target: TaskRequestUpdateTarget
  message: string
}

/**
 * Discriminated form mode shared by the form hook/meta-resolver and
 * `TaskForm`. `parentTaskId` carries the "crea sotto-task" prefill (AC-085):
 * present means the parent picker renders prefilled AND locked.
 */
export type TaskFormMode =
  | {
      type: 'create'
      parentTaskId?: number | null
      workOrderId?: number | null
      /** Spec 0146 AC-030: prefill for the "Fase" select, from `ModuleCreateParams.work_order_stage_id` (the Task board's "+ Task"). */
      workOrderStageId?: number | null
      /** Spec 0157 D-4: prefill for the "Stato" select, from `ModuleCreateParams.task_status_id` (the Kanban per-column "+"). */
      taskStatusId?: number | null
      /** Spec 0157 D-4: prefill for the "Data fine" field, from `ModuleCreateParams.end_date` (the Kanban per-column "+"). */
      endDate?: string | null
    }
  | { type: 'edit'; task: TaskDetailWithPermissions }
  /**
   * Row action "duplicate" (spec 0156 D-4): the create form pre-filled from
   * `source`, itself still a fresh, re-authorized fetch (mirrors
   * `CampaignFormMode`'s own `duplicate` branch). Copies every field but
   * attachments, sub-tasks, status (re-derived), `completion_date`, segnatempo
   * and the parent link — `useTaskForm`'s `duplicateDefaults` is the single
   * place enforcing exactly that list.
   */
  | { type: 'duplicate'; source: TaskDetailWithPermissions }
