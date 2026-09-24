/**
 * Shared fixtures for the Task module's tests: one builder per shape of the
 * frozen `data_contract` (spec 0101), so a contract change is edited in ONE
 * place instead of drifting across test files.
 */

import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import type {
  TaskDetail,
  TaskDetailWithPermissions,
  TaskRecurrenceDetail,
  TaskStatusRef,
} from '@/features/tasks/types'
import type { TaskFormValues } from '@/features/tasks/task-schema'

export const EDITABLE_FIELD: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

/** No field entry at all: `useResourcePermissions` falls back to visible + editable. */
export const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

export function taskStatus(overrides: Partial<TaskStatusRef> = {}): TaskStatusRef {
  return {
    id: 3,
    name: 'In lavorazione',
    color: 'blue',
    icon: null,
    system_key: 'open',
    group: 'open',
    completion_percentage: 25,
    ...overrides,
  }
}

/** Disabled-by-default `recurrence` slice, merge in the fields a given test actually needs. */
export function taskRecurrenceFormValues(
  overrides: Partial<TaskFormValues['recurrence']> = {},
): TaskFormValues['recurrence'] {
  return {
    enabled: false,
    frequency: null,
    interval: 1,
    weekdays: [],
    month_day: null,
    ends: null,
    ends_on: null,
    occurrence_count: null,
    ...overrides,
  }
}

export function taskRecurrenceDetail(overrides: Partial<TaskRecurrenceDetail> = {}): TaskRecurrenceDetail {
  return {
    id: 501,
    frequency: 'weekly',
    interval: 2,
    weekdays: [1, 3],
    month_day: null,
    ends: 'on_date',
    ends_on: '2027-03-31',
    occurrence_count: null,
    ...overrides,
  }
}

export function taskFormValues(overrides: Partial<TaskFormValues> = {}): TaskFormValues {
  return {
    title: 'Richiamare il cliente',
    task_status_id: 3,
    description: 'Chiamata di verifica',
    is_private: false,
    evidence: null,
    registry_id: 7,
    referent_id: 11,
    parent_task_id: null,
    task_type_id: 2,
    task_priority_id: 4,
    task_importance_id: 5,
    task_category_id: 6,
    opportunity_id: 8,
    work_order_id: 9,
    work_order_stage_id: null,
    lead_id: null,
    requester_id: 21,
    start_date: '2026-09-01',
    end_date: '2026-09-05',
    start_time: '09:00',
    end_time: '10:30',
    estimated_minutes: 90,
    requires_closure_feedback: false,
    requires_validation: false,
    is_completed: false,
    suppress_notifications: false,
    assignee_ids: [31, 32],
    watcher_ids: [41],
    recurrence: taskRecurrenceFormValues(),
    ...overrides,
  }
}

export function taskDetail(overrides: Partial<TaskDetail> = {}): TaskDetail {
  return {
    id: 90,
    title: 'Richiamare il cliente',
    description: 'Chiamata di verifica',
    is_private: false,
    evidence: null,
    registry_id: 7,
    registry: { id: 7, name: 'Acme' },
    referent_id: 11,
    referent: { id: 11, name: 'Ada Alberti' },
    parent_task_id: null,
    parent_task: null,
    task_type_id: 2,
    task_type: null,
    task_status_id: 3,
    task_status: taskStatus(),
    task_priority_id: 4,
    task_priority: null,
    task_importance_id: 5,
    task_importance: null,
    task_category_id: 6,
    task_category: null,
    opportunity_id: 8,
    opportunity: null,
    work_order_id: 9,
    work_order: null,
    work_order_stage_id: null,
    work_order_stage: null,
    lead_id: null,
    lead: null,
    requester_id: 21,
    requester: { id: 21, name: 'Bruno Bianchi' },
    creator: { id: 1, name: 'Carla Conti' },
    assignees: [
      { id: 31, name: 'Dario Dini' },
      { id: 32, name: 'Elsa Esposito' },
    ],
    watchers: [{ id: 41, name: 'Fabio Fini' }],
    start_date: '2026-09-01',
    end_date: '2026-09-05',
    completion_date: null,
    start_time: '09:00',
    end_time: '10:30',
    estimated_minutes: 90,
    recurrence: null,
    is_blocked: false,
    requires_closure_feedback: false,
    requires_validation: false,
    closure_feedback: null,
    completion_percentage: 25,
    open_subtasks_count: 0,
    subtasks: [],
    created_at: '2026-09-01T08:00:00Z',
    updated_at: '2026-09-01T08:00:00Z',
    ...overrides,
  }
}

export function taskDetailWithPermissions(
  overrides: Partial<TaskDetailWithPermissions> = {},
): TaskDetailWithPermissions {
  return { ...taskDetail(), permissions: FULL_ACCESS_PERMISSIONS, ...overrides }
}
