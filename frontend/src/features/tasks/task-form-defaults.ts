import { emptyRecurrenceDefaults, recurrenceDefaults } from '@/features/tasks/task-recurrence-defaults'
import { resolveWorkOrderStagePrefill } from '@/features/tasks/use-task-form-stage-handlers'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskDetail } from '@/features/tasks/types'

/*
 * The three default-value builders of the task form (create, "clona", edit),
 * split out of `use-task-form.ts` purely for its size budget (engineering.md
 * §6): pure functions of their inputs, no hook state.
 */

/** Stable module-level default: a fresh `[]` per render would break dependency stability. */
const EMPTY_IDS: number[] = []
/** Spec 0155 D-3: the create-only "Sottotask" block starts empty; edit mode never touches this field. */
const EMPTY_SUBTASKS: TaskFormValues['subtasks'] = []

/** Today as `YYYY-MM-DD` in the ACTOR's own local calendar day (not UTC: a `Y-m-d` due date compares as a plain string). */
function todayIsoDate(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

/**
 * Default values of a brand-new task, with the "crea sotto-task" parent
 * prefill (AC-085), the work order prefill of the Commessa detail's Task tab
 * (spec 0133 D-4), the anagrafica prefill of the anagrafica's Task tab (spec
 * 0199) and the requester defaulted to the actor creating it
 * (spec 0118 D-1: `requester_id` is now required, and the actor is the
 * requester in the overwhelming majority of cases) — left modifiable, never
 * locked.
 */
export function createDefaults(
  parentTaskId: number | null,
  workOrderId: number | null,
  workOrderStageId: number | null,
  requesterId: number | null,
  taskStatusId: number | null,
  endDate: string | null,
  registryId: number | null,
): TaskFormValues {
  // Spec 0154 D-9: today, UNLESS this create is opened as "crea sotto-task",
  // from a commessa's Task tab, or with an explicit `endDate` prefill (spec
  // 0157 D-4, the Kanban per-column "+") — all three contexts prefill their
  // own date range/end date, so this default steps aside for them.
  const hasLinkContext = parentTaskId !== null || workOrderId !== null || endDate !== null
  return {
    title: '',
    task_status_id: taskStatusId,
    description: null,
    is_private: false,
    registry_id: registryId,
    referent_id: null,
    parent_task_id: parentTaskId,
    task_type_id: null,
    task_priority_id: null,
    task_importance_id: null,
    task_category_id: null,
    opportunity_id: null,
    work_order_id: workOrderId,
    work_order_stage_id: resolveWorkOrderStagePrefill(parentTaskId, workOrderStageId),
    lead_id: null,
    requester_id: requesterId,
    start_date: null,
    end_date: endDate ?? (hasLinkContext ? null : todayIsoDate()),
    start_time: null,
    end_time: null,
    estimated_minutes: null,
    requires_closure_feedback: false,
    requires_validation: false,
    is_completed: false,
    suppress_notifications: false,
    assignee_ids: EMPTY_IDS,
    watcher_ids: EMPTY_IDS,
    recurrence: emptyRecurrenceDefaults(),
    subtasks: EMPTY_SUBTASKS,
  }
}

/**
 * Default values of a "clona" create (spec 0156 D-4): every field copied from
 * `source` EXCEPT the ones the decision explicitly excludes — attachments and
 * segnatempo have no form field to begin with, `subtasks` stays the empty
 * create-only block, the parent link is dropped (`parent_task_id: null`,
 * "il padre non si copia"), `task_status_id` stays unset so the server
 * RE-DERIVES it exactly like a bare create (D-4 "stato ricalcolato"), and
 * `is_completed`/`suppress_notifications` — both per-submit instructions with
 * no persisted counterpart — start unchecked like any other create.
 */
export function duplicateDefaults(source: TaskDetail, copySuffix: string): TaskFormValues {
  return {
    title: `${source.title}${copySuffix}`,
    task_status_id: null,
    description: source.description,
    is_private: source.is_private,
    registry_id: source.registry_id,
    referent_id: source.referent_id,
    parent_task_id: null,
    task_type_id: source.task_type_id,
    task_priority_id: source.task_priority_id,
    task_importance_id: source.task_importance_id,
    task_category_id: source.task_category_id,
    opportunity_id: source.opportunity_id,
    work_order_id: source.work_order_id,
    work_order_stage_id: source.work_order_stage_id,
    lead_id: source.lead_id,
    requester_id: source.requester_id,
    start_date: source.start_date,
    end_date: source.end_date ?? todayIsoDate(),
    start_time: source.start_time,
    end_time: source.end_time,
    estimated_minutes: source.estimated_minutes,
    requires_closure_feedback: source.requires_closure_feedback,
    requires_validation: source.requires_validation,
    is_completed: false,
    suppress_notifications: false,
    assignee_ids: source.assignees.map((user) => user.id),
    watcher_ids: source.watchers.map((user) => user.id),
    recurrence: recurrenceDefaults(source.recurrence),
    subtasks: EMPTY_SUBTASKS,
  }
}

/** Default values hydrated from the persisted task (edit mode). */
export function editDefaults(task: TaskDetail): TaskFormValues {
  return {
    title: task.title,
    task_status_id: task.task_status_id,
    description: task.description,
    is_private: task.is_private,
    registry_id: task.registry_id,
    referent_id: task.referent_id,
    parent_task_id: task.parent_task_id,
    task_type_id: task.task_type_id,
    task_priority_id: task.task_priority_id,
    task_importance_id: task.task_importance_id,
    task_category_id: task.task_category_id,
    opportunity_id: task.opportunity_id,
    work_order_id: task.work_order_id,
    work_order_stage_id: task.work_order_stage_id,
    lead_id: task.lead_id,
    requester_id: task.requester_id,
    start_date: task.start_date,
    end_date: task.end_date,
    start_time: task.start_time,
    end_time: task.end_time,
    estimated_minutes: task.estimated_minutes,
    requires_closure_feedback: task.requires_closure_feedback,
    requires_validation: task.requires_validation,
    // Spec 0154 D-6/D-7: neither has a persisted counterpart — both are
    // per-submit instructions, so edit mode always starts them unset.
    is_completed: false,
    suppress_notifications: false,
    assignee_ids: task.assignees.map((user) => user.id),
    watcher_ids: task.watchers.map((user) => user.id),
    recurrence: recurrenceDefaults(task.recurrence),
    subtasks: EMPTY_SUBTASKS,
  }
}
