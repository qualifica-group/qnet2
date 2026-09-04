import { sameIdSet } from '@/lib/utils'
import type { CreateTaskPayload, TaskDetail, UpdateTaskPayload } from '@/features/tasks/types'
import type { TaskFormValues } from '@/features/tasks/task-schema'

/**
 * The scalars that map 1:1 from form values onto the wire, in the frozen
 * contract's own order. Extracted so create and the PATCH diff below read the
 * SAME projection and can never disagree on which fields exist.
 * `completion_percentage` and `creator_id` are absent from `TaskFormValues`
 * altogether (D-6/D-10), so no builder can leak them (AC-011/AC-084).
 */
function scalarsOf(values: TaskFormValues) {
  return {
    title: values.title,
    description: values.description,
    registry_id: values.registry_id,
    referent_id: values.referent_id,
    parent_task_id: values.parent_task_id,
    task_type_id: values.task_type_id,
    task_priority_id: values.task_priority_id,
    task_importance_id: values.task_importance_id,
    task_category_id: values.task_category_id,
    opportunity_id: values.opportunity_id,
    work_order_id: values.work_order_id,
    requester_id: values.requester_id,
    start_date: values.start_date,
    end_date: values.end_date,
    completion_date: values.completion_date,
    start_time: values.start_time,
    end_time: values.end_time,
    estimated_minutes: values.estimated_minutes,
    is_blocked: values.is_blocked,
    requires_closure_feedback: values.requires_closure_feedback,
    closure_feedback: values.closure_feedback,
  }
}

/**
 * Builds the create payload. `task_status_id` is required by the contract and
 * guaranteed non-null by the schema's own refinement, so the cast states that
 * postcondition rather than assuming it.
 */
export function buildCreatePayload(values: TaskFormValues): CreateTaskPayload {
  return {
    ...scalarsOf(values),
    task_status_id: values.task_status_id as number,
    // Flat id arrays (AC-083); always sent on create — there is nothing
    // persisted to preserve, so the sparse-sync rule below does not apply.
    assignee_ids: values.assignee_ids,
    watcher_ids: values.watcher_ids,
  }
}

/**
 * Builds a partial PATCH payload carrying only what actually changed from the
 * persisted task. The two user arrays travel ONLY when the selected set
 * differs (AC-012: an untouched selection must not resend a no-op sync, while
 * an emptied one still sends `[]` and clears the pivot).
 */
export function buildUpdatePayload(values: TaskFormValues, original: TaskDetail): UpdateTaskPayload {
  const payload: UpdateTaskPayload = {}
  const scalars = scalarsOf(values)

  if (scalars.title !== original.title) payload.title = scalars.title
  if (scalars.description !== original.description) payload.description = scalars.description
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
  if (scalars.requester_id !== original.requester_id) payload.requester_id = scalars.requester_id
  if (scalars.start_date !== original.start_date) payload.start_date = scalars.start_date
  if (scalars.end_date !== original.end_date) payload.end_date = scalars.end_date
  if (scalars.completion_date !== original.completion_date) payload.completion_date = scalars.completion_date
  if (scalars.start_time !== original.start_time) payload.start_time = scalars.start_time
  if (scalars.end_time !== original.end_time) payload.end_time = scalars.end_time
  if (scalars.estimated_minutes !== original.estimated_minutes) {
    payload.estimated_minutes = scalars.estimated_minutes
  }
  if (scalars.is_blocked !== original.is_blocked) payload.is_blocked = scalars.is_blocked
  if (scalars.requires_closure_feedback !== original.requires_closure_feedback) {
    payload.requires_closure_feedback = scalars.requires_closure_feedback
  }
  if (scalars.closure_feedback !== original.closure_feedback) payload.closure_feedback = scalars.closure_feedback

  if (values.task_status_id !== null && values.task_status_id !== original.task_status_id) {
    payload.task_status_id = values.task_status_id
  }
  if (!sameIdSet(values.assignee_ids, original.assignees.map((user) => user.id))) {
    payload.assignee_ids = values.assignee_ids
  }
  if (!sameIdSet(values.watcher_ids, original.watchers.map((user) => user.id))) {
    payload.watcher_ids = values.watcher_ids
  }

  return payload
}
