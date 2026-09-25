/**
 * Task write-side payload types (spec 0101/0118/0121/0153/0154/0155): the
 * shapes `POST|PATCH /api/tasks` and its sibling action endpoints accept.
 * Split out of `types.ts` purely to keep that file under the engineering.md
 * §6 size budget; `types.ts` re-exports everything here so existing callers
 * keep importing from there.
 */

import type { CreateTaskSubtaskPayload } from '@/features/tasks/task-subtask-types'
import type { TaskRecurrencePayload } from '@/features/tasks/task-recurrence-types'

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
