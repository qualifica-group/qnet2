import { sameIdSet } from '@/lib/utils'
import type {
  CreateTaskPayload,
  CreateTaskSubtaskPayload,
  TaskDetail,
  TaskRecurrenceDetail,
  TaskRecurrencePayload,
  UpdateTaskPayload,
} from '@/features/tasks/types'
import type { TaskFormValues } from '@/features/tasks/task-schema'

/**
 * The form's `recurrence` slice, reduced to what the wire actually cares
 * about: non-pertinent fields NULLED per D-1 (`weekdays` outside `weekly`,
 * `month_day` outside `monthly`, `ends_on`/`occurrence_count` outside their
 * own `ends` branch) so two rules that only differ on an ignored field
 * compare equal. `null` means "no recurrence at all" — `enabled: false`, or a
 * `frequency`/`ends` still unset (can't happen once AC-033 passed, guarded
 * here anyway rather than assumed).
 */
type RecurrenceRule = Omit<TaskRecurrenceDetail, 'id'>

function normalizeRecurrenceValues(recurrence: TaskFormValues['recurrence']): RecurrenceRule | null {
  if (!recurrence.enabled || recurrence.frequency === null || recurrence.ends === null) {
    return null
  }
  const { frequency } = recurrence
  const isMonthlyOrYearly = frequency === 'monthly' || frequency === 'yearly'
  // Spec 0155 D-1: a `month_mode` not yet picked defaults to `fixed` — the
  // section seeds it explicitly on frequency pick, this fallback only
  // matters for a rule built without going through that picker.
  const monthMode = isMonthlyOrYearly ? (recurrence.month_mode ?? 'fixed') : null
  return {
    frequency,
    interval: recurrence.interval ?? 1,
    weekdays: frequency === 'weekly' ? recurrence.weekdays : null,
    month_mode: monthMode,
    month_day: isMonthlyOrYearly && monthMode === 'fixed' ? recurrence.month_day : null,
    ordinal: isMonthlyOrYearly && monthMode === 'ordinal' ? recurrence.ordinal : null,
    ordinal_weekday: isMonthlyOrYearly && monthMode === 'ordinal' ? recurrence.ordinal_weekday : null,
    year_month: frequency === 'yearly' ? recurrence.year_month : null,
    workdays_only: recurrence.workdays_only,
    ends: recurrence.ends,
    ends_on: recurrence.ends === 'on_date' ? recurrence.ends_on : null,
    occurrence_count: recurrence.ends === 'after_count' ? recurrence.occurrence_count : null,
  }
}

/** The persisted `TaskRecurrenceDetail` reduced to the same comparable shape, `id` dropped. */
function normalizeRecurrenceDetail(detail: TaskRecurrenceDetail | null): RecurrenceRule | null {
  if (!detail) {
    return null
  }
  return {
    frequency: detail.frequency,
    interval: detail.interval,
    weekdays: detail.weekdays,
    month_mode: detail.month_mode,
    month_day: detail.month_day,
    ordinal: detail.ordinal,
    ordinal_weekday: detail.ordinal_weekday,
    year_month: detail.year_month,
    workdays_only: detail.workdays_only,
    ends: detail.ends,
    ends_on: detail.ends_on,
    occurrence_count: detail.occurrence_count,
  }
}

function sameRecurrenceRule(a: RecurrenceRule | null, b: RecurrenceRule | null): boolean {
  if (a === null || b === null) {
    return a === b
  }
  return (
    a.frequency === b.frequency &&
    a.interval === b.interval &&
    a.month_mode === b.month_mode &&
    a.month_day === b.month_day &&
    a.ordinal === b.ordinal &&
    a.ordinal_weekday === b.ordinal_weekday &&
    a.year_month === b.year_month &&
    a.workdays_only === b.workdays_only &&
    a.ends === b.ends &&
    a.ends_on === b.ends_on &&
    a.occurrence_count === b.occurrence_count &&
    sameIdSet(a.weekdays ?? [], b.weekdays ?? [])
  )
}

/**
 * Projects a normalized rule onto the wire shape: pertinent fields only (D-1
 * `prohibited_unless`). `normalizeRecurrenceValues` already nulled out every
 * field not pertinent to the picked `frequency`/`month_mode`/`ends`, so this
 * stays a flat "send what survived" projection (spec 0155 D-1).
 */
function recurrencePayloadOf(rule: RecurrenceRule): TaskRecurrencePayload {
  const payload: TaskRecurrencePayload = {
    frequency: rule.frequency,
    interval: rule.interval,
    ends: rule.ends,
  }
  if (rule.frequency === 'weekly') {
    payload.weekdays = rule.weekdays ?? []
  }
  if (rule.month_mode !== null) {
    payload.month_mode = rule.month_mode
  }
  if (rule.month_day !== null) {
    payload.month_day = rule.month_day
  }
  if (rule.ordinal !== null) {
    payload.ordinal = rule.ordinal
  }
  if (rule.ordinal_weekday !== null) {
    payload.ordinal_weekday = rule.ordinal_weekday
  }
  if (rule.year_month !== null) {
    payload.year_month = rule.year_month
  }
  if (rule.workdays_only) {
    payload.workdays_only = true
  }
  if (rule.ends === 'on_date' && rule.ends_on !== null) {
    payload.ends_on = rule.ends_on
  }
  if (rule.ends === 'after_count' && rule.occurrence_count !== null) {
    payload.occurrence_count = rule.occurrence_count
  }
  return payload
}

/**
 * Spec 0155 D-3: one "Sottotask" row onto the wire shape — `title` is the
 * only field the compact create-form block collects besides the optional
 * due date and assignees; every other `CreateTaskSubtaskPayload` field is
 * left for the server's own inheritance from the parent.
 */
function subtaskPayloadOf(row: TaskFormValues['subtasks'][number]): CreateTaskSubtaskPayload {
  const payload: CreateTaskSubtaskPayload = { title: row.title.trim() }
  if (row.end_date) {
    payload.end_date = row.end_date
  }
  if (row.assignee_ids.length > 0) {
    payload.assignee_ids = row.assignee_ids
  }
  return payload
}

/**
 * The scalars that map 1:1 from form values onto the wire, in the frozen
 * contract's own order. Extracted so create and the PATCH diff below read the
 * SAME projection and can never disagree on which fields exist.
 * `completion_percentage`, `creator_id` and `is_blocked` are absent from
 * `TaskFormValues` altogether (spec 0101 D-6/D-10, spec 0116 D-6), so no
 * builder can leak them (AC-011/AC-084/AC-045).
 */
function scalarsOf(values: TaskFormValues) {
  return {
    title: values.title,
    description: values.description,
    is_private: values.is_private,
    evidence: values.evidence,
    registry_id: values.registry_id,
    referent_id: values.referent_id,
    parent_task_id: values.parent_task_id,
    task_type_id: values.task_type_id,
    task_priority_id: values.task_priority_id,
    task_importance_id: values.task_importance_id,
    task_category_id: values.task_category_id,
    opportunity_id: values.opportunity_id,
    work_order_id: values.work_order_id,
    work_order_stage_id: values.work_order_stage_id,
    lead_id: values.lead_id,
    requester_id: values.requester_id,
    start_date: values.start_date,
    end_date: values.end_date,
    start_time: values.start_time,
    end_time: values.end_time,
    estimated_minutes: values.estimated_minutes,
    requires_closure_feedback: values.requires_closure_feedback,
    requires_validation: values.requires_validation,
  }
}

/**
 * Builds the create payload. `task_status_id` is DELIBERATELY absent (spec 0118
 * D-3): the initial status is derived server-side from the assignees (D-4) and is
 * `prohibited` on POST, so a create payload carrying it would be a 422.
 *
 * `requester_id` and `end_date` are required by the contract (D-1) and guaranteed
 * non-null by the schema's own refinements, so the casts state that postcondition
 * rather than assuming it — the same idiom this builder already used for the
 * status it no longer sends.
 *
 * `canEditRecurrence` (spec 0120 D-12) defaults to `true`, the same permissive
 * fallback `useResourcePermissions()` itself uses for a field it knows
 * nothing about — every EXISTING caller that never heard of this field keeps
 * sending it exactly as before. A caller that HAS the actor's permission
 * passes it explicitly; `false` drops the key outright, never sends `null`.
 */
export function buildCreatePayload(
  values: TaskFormValues,
  canEditRecurrence: boolean = true,
): CreateTaskPayload {
  const payload: CreateTaskPayload = {
    ...scalarsOf(values),
    requester_id: values.requester_id as number,
    end_date: values.end_date as string,
    // Always sent on create — there is nothing persisted to preserve, so the
    // sparse-sync rule below does not apply. Since D-9 the two sets are disjoint.
    assignee_ids: values.assignee_ids,
    watcher_ids: values.watcher_ids,
  }
  // Spec 0154 D-10: a manually picked initial status; omitted, the server
  // derives it as before (0118 D-4).
  if (values.task_status_id !== null) {
    payload.task_status_id = values.task_status_id
  }
  // Spec 0154 D-6: "Crea gia' completato" is a create-only instruction, sent
  // only when checked — the server default (`false`) covers the common case.
  if (values.is_completed) {
    payload.is_completed = true
  }
  // Spec 0154 D-7: the ONE UI toggle maps onto `notify_assigned_users` on
  // create; sent only when the actor actively suppresses the notification,
  // the server default (`true`) covers the unchecked case.
  if (values.suppress_notifications) {
    payload.notify_assigned_users = false
  }
  if (canEditRecurrence) {
    const rule = normalizeRecurrenceValues(values.recurrence)
    payload.recurrence = rule ? recurrencePayloadOf(rule) : null
  }
  // Spec 0155 D-3: create-only bulk sub-tasks; an empty block sends no key
  // at all rather than an empty array (mirrors every other opt-in list here).
  if (values.subtasks.length > 0) {
    payload.subtasks = values.subtasks.map(subtaskPayloadOf)
  }
  return payload
}

/**
 * Builds a partial PATCH payload carrying only what actually changed from the
 * persisted task. The two user arrays travel ONLY when the selected set
 * differs (AC-012: an untouched selection must not resend a no-op sync, while
 * an emptied one still sends `[]` and clears the pivot).
 *
 * `canEditRecurrence` (spec 0120 D-12, see `buildCreatePayload`): the key is
 * added ONLY when both the actor may write it AND the normalized rule
 * actually differs from the persisted one — an untouched section must stay
 * ABSENT from the diff (D-10: "chiave assente = invariata"), the same
 * "nothing changed, nothing sent" contract every other field already keeps.
 */
export function buildUpdatePayload(
  values: TaskFormValues,
  original: TaskDetail,
  canEditRecurrence: boolean = true,
): UpdateTaskPayload {
  const payload: UpdateTaskPayload = {}
  const scalars = scalarsOf(values)

  if (scalars.title !== original.title) payload.title = scalars.title
  if (scalars.description !== original.description) payload.description = scalars.description
  if (scalars.is_private !== original.is_private) payload.is_private = scalars.is_private
  if (scalars.evidence !== original.evidence) payload.evidence = scalars.evidence
  if (scalars.registry_id !== original.registry_id) payload.registry_id = scalars.registry_id
  if (scalars.referent_id !== original.referent_id) payload.referent_id = scalars.referent_id
  if (scalars.parent_task_id !== original.parent_task_id) payload.parent_task_id = scalars.parent_task_id
  if (scalars.task_type_id !== original.task_type_id) payload.task_type_id = scalars.task_type_id
  if (scalars.task_priority_id !== original.task_priority_id) payload.task_priority_id = scalars.task_priority_id
  if (scalars.task_importance_id !== original.task_importance_id) {
    payload.task_importance_id = scalars.task_importance_id
  }
  if (scalars.task_category_id !== original.task_category_id) payload.task_category_id = scalars.task_category_id
  if (scalars.opportunity_id !== original.opportunity_id) payload.opportunity_id = scalars.opportunity_id
  if (scalars.work_order_id !== original.work_order_id) payload.work_order_id = scalars.work_order_id
  if (scalars.work_order_stage_id !== original.work_order_stage_id) {
    payload.work_order_stage_id = scalars.work_order_stage_id
  }
  if (scalars.lead_id !== original.lead_id) payload.lead_id = scalars.lead_id
  // D-2: `requester_id` and `end_date` are `sometimes|required` on PATCH — not
  // annullable. A null here would be a 422, so it is simply not sent: the guard
  // is the contract, not a convenience.
  if (scalars.requester_id !== original.requester_id && scalars.requester_id !== null) {
    payload.requester_id = scalars.requester_id
  }
  if (scalars.start_date !== original.start_date) payload.start_date = scalars.start_date
  if (scalars.end_date !== original.end_date && scalars.end_date !== null) {
    payload.end_date = scalars.end_date
  }
  if (scalars.start_time !== original.start_time) payload.start_time = scalars.start_time
  if (scalars.end_time !== original.end_time) payload.end_time = scalars.end_time
  if (scalars.estimated_minutes !== original.estimated_minutes) {
    payload.estimated_minutes = scalars.estimated_minutes
  }
  if (scalars.requires_closure_feedback !== original.requires_closure_feedback) {
    payload.requires_closure_feedback = scalars.requires_closure_feedback
  }
  if (scalars.requires_validation !== original.requires_validation) {
    payload.requires_validation = scalars.requires_validation
  }

  if (values.task_status_id !== null && values.task_status_id !== original.task_status_id) {
    payload.task_status_id = values.task_status_id
  }
  if (!sameIdSet(values.assignee_ids, original.assignees.map((user) => user.id))) {
    payload.assignee_ids = values.assignee_ids
  }
  if (!sameIdSet(values.watcher_ids, original.watchers.map((user) => user.id))) {
    payload.watcher_ids = values.watcher_ids
  }

  // Spec 0154 D-7: the edit-mode sibling of the create toggle — no persisted
  // counterpart to diff against, so it is sent only while actively checked.
  if (values.suppress_notifications) {
    payload.notify_new_assigned_users = false
  }

  if (canEditRecurrence) {
    const rule = normalizeRecurrenceValues(values.recurrence)
    if (!sameRecurrenceRule(rule, normalizeRecurrenceDetail(original.recurrence))) {
      payload.recurrence = rule ? recurrencePayloadOf(rule) : null
    }
  }

  return payload
}
