import type { UseFormSetValue } from 'react-hook-form'
import type { TaskFormValues } from '@/features/tasks/task-schema'

/**
 * Spec 0146 D-3: a "crea sotto-task" prefill always wins over the fase
 * prefill — a sub-task can never carry a `work_order_stage_id` at all.
 * Pure so `createDefaults` (`use-task-form.ts`) can call it without pulling
 * in the handlers below.
 */
export function resolveWorkOrderStagePrefill(
  parentTaskId: number | null,
  workOrderStageId: number | null,
): number | null {
  return parentTaskId === null ? workOrderStageId : null
}

export interface TaskFormStageHandlers {
  handleWorkOrderChange: () => void
  handleParentChange: () => void
}

/**
 * The "Fase" field's two reset handlers (spec 0146 D-3), split out of
 * `useTaskForm` for file size (engineering.md §6) — both still write through
 * the SAME form instance `useTaskForm` owns, just via `setValue`.
 *
 * `handleWorkOrderChange`: the fase belongs to the picked commessa, so a
 * pick/clear here invalidates whatever fase was selected.
 * `handleParentChange`: a sub-task's fase is `prohibited` server-side —
 * picking a parent clears it up front rather than inviting a 422 on submit.
 *
 * Both fire from the field's own change HANDLER, never from an effect that
 * could race a later edit (mirrors `useTaskForm`'s own `handleRegistryChange`).
 */
export function useTaskFormStageHandlers(setValue: UseFormSetValue<TaskFormValues>): TaskFormStageHandlers {
  const handleWorkOrderChange = () => {
    setValue('work_order_stage_id', null, { shouldDirty: true })
  }

  const handleParentChange = () => {
    setValue('work_order_stage_id', null, { shouldDirty: true })
  }

  return { handleWorkOrderChange, handleParentChange }
}
