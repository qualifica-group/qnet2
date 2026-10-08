import { useResourcePermissions } from '@/features/authorization/permissions'
import type { FieldPermission } from '@/features/authorization/types'
import type { TaskDetail } from '@/features/tasks/task-detail-types'

type TaskCascadeField =
  | 'registry_id'
  | 'referent_id'
  | 'opportunity_id'
  | 'lead_id'
  | 'work_order_id'
  | 'work_order_stage_id'

interface TaskCascade {
  /** Reset to `null` by the editor's handlers: blocking only while they hold a value (an unchanged `null` is no PATCH key). */
  clears: readonly TaskCascadeField[]
  /** Given a new value by the editor's handlers: always blocking. */
  sets?: readonly TaskCascadeField[]
}

/**
 * What each detail editor's change handlers write BESIDES its own field —
 * mirrors `useTaskForm`'s `handleRegistryChange`/`handleWorkOrderItemChange`/
 * `handleOpportunityChange` (spec 0154 D-11) and the fase resets (spec 0146
 * D-3). The diff PATCH carries those keys too, so each must be editable.
 */
const TASK_CASCADES: Readonly<Partial<Record<string, TaskCascade>>> = {
  registry_id: { clears: ['referent_id', 'opportunity_id', 'lead_id', 'work_order_id', 'work_order_stage_id'] },
  work_order_id: { clears: ['work_order_stage_id', 'opportunity_id'], sets: ['registry_id'] },
  opportunity_id: { clears: ['work_order_id', 'work_order_stage_id'] },
  parent_task_id: { clears: ['work_order_stage_id'] },
}

/**
 * Whether `field`'s editor may open without its cascade writing a field the
 * actor cannot change (spec 0195): otherwise the save would always be
 * refused (422 "field not editable" on the dependent key).
 */
export function isTaskCascadeEditable(
  field: string,
  task: TaskDetail,
  fieldPermission: (key: string) => FieldPermission,
): boolean {
  const cascade = TASK_CASCADES[field]
  if (!cascade) {
    return true
  }
  const isEditable = (key: TaskCascadeField) => {
    const permission = fieldPermission(key)
    return permission.editable && !permission.disabled
  }

  return (
    cascade.clears.every((key) => task[key] === null || isEditable(key)) && (cascade.sets ?? []).every(isEditable)
  )
}

/** `isTaskCascadeEditable` bound to the detail's own field permissions. */
export function useTaskCascadeEditable(task: TaskDetail): (field: string) => boolean {
  const { field } = useResourcePermissions()
  return (key) => isTaskCascadeEditable(key, task, field)
}
