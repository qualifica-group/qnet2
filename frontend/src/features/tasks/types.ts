/**
 * Task module CRUD types (spec 0101). The generic table types (columns/
 * filters/actions/rows) live in `features/table/types.ts`; this file holds
 * only what is genuinely task-specific. Source of truth: the frozen
 * `data_contract` of `GET|POST|PATCH /api/tasks` (`TaskResource`).
 *
 * Split by area into `task-detail-types.ts` (the read-side resource shape)
 * and `task-payload-types.ts` (the write-side payloads), purely to keep this
 * file under the engineering.md §6 size budget — re-exported here so every
 * existing `from '@/features/tasks/types'` import keeps working unchanged.
 */

import type { TaskDetailWithPermissions } from '@/features/tasks/task-detail-types'

export type { CreateTaskSubtaskPayload } from '@/features/tasks/task-subtask-types'

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

export {
  type TaskStatusSystemKey,
  type TaskLookupRef,
  type TaskStatusRef,
  type TaskNamedRef,
  type TaskLeadRef,
  type TaskParentRef,
  type TaskWorkOrderRef,
  type TaskWorkOrderStageRef,
  type TaskSubtask,
  type TaskDetail,
  type TaskDetailWithPermissions,
  type TaskActionKey,
} from '@/features/tasks/task-detail-types'

export {
  type CompleteTaskTimeEntryPayload,
  type CompleteTaskPayload,
  type CreateTaskPayload,
  type UpdateTaskPayload,
  type TaskRequestUpdateTarget,
  type RequestTaskUpdatePayload,
} from '@/features/tasks/task-payload-types'

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
