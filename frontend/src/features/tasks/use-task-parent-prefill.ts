/**
 * "Eredita' dei collegamenti, solo client" (spec 0123 D-10) + the D-7
 * date-range mirror it feeds (`task-parent-date-range.ts`): both create-only,
 * both driven by the SAME parent detail fetch. `fetchTask` via `useQuery`,
 * never `useEffect` for the request itself (`react-hooks.md`) — the effect
 * below only SYNCS the already-fetched data into the form, exactly the class
 * of side effect `useEffect` exists for.
 */

import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useWatch } from 'react-hook-form'
import type { Control, UseFormGetValues, UseFormSetValue } from 'react-hook-form'
import { fetchTask, taskDetailQueryKey } from '@/features/tasks/api'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ParentDateRange } from '@/features/tasks/task-parent-date-range'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

interface UseTaskParentPrefillArgs {
  control: Control<TaskFormValues>
  setValue: UseFormSetValue<TaskFormValues>
  getValues: UseFormGetValues<TaskFormValues>
  /** Spec 0123 D-10: the link prefill and its D-7 date-range mirror are CREATE-only. */
  enabled: boolean
}

interface TaskParentPrefillResult {
  /** Feeds `buildTaskSchema`'s D-7 mirror; `null` while disabled or before the parent loads. */
  parentDateRange: ParentDateRange | null
  registry: RelationFieldRef | null
  referent: RelationFieldRef | null
  opportunity: RelationFieldRef | null
  workOrder: RelationFieldRef | null
}

/** `{id, code, title}` projected onto `{id, name}`, matching `task-form-body.tsx`'s own `workOrderRefOf`. */
function workOrderRefOf(parent: TaskDetailWithPermissions | undefined): RelationFieldRef | null {
  const workOrder = parent?.work_order
  if (!workOrder) {
    return null
  }
  const title = workOrder.title.trim()
  return { id: workOrder.id, name: title === '' ? workOrder.code : `${workOrder.code} — ${title}` }
}

export function useTaskParentPrefill({
  control,
  setValue,
  getValues,
  enabled,
}: UseTaskParentPrefillArgs): TaskParentPrefillResult {
  const parentTaskId = useWatch({ control, name: 'parent_task_id' })

  // The query key doubles as the Task detail's own cache key: a parent
  // already open elsewhere in the session needs no second request. Disabled
  // (and thus never actually run) while there is no parent to read.
  // `refetchOnMount: false`: the parent detail behind the "crea sotto-task"
  // Sheet observes this same key and shows its skeleton on every fetch, so a
  // cached parent is reused as-is; an uncached one is still fetched.
  const parentQuery = useQuery({
    queryKey: taskDetailQueryKey(parentTaskId ?? 0),
    queryFn: () => fetchTask(parentTaskId as number),
    enabled: enabled && parentTaskId !== null,
    refetchOnMount: false,
  })
  const parent = parentQuery.data

  // AC-037/AC-038: each of the four fields prefills ONLY while still empty —
  // never overwriting a value the user (or a previous parent pick) already
  // set. Re-runs on every new parent, so switching the picker mid-creation
  // still fills whatever remains empty without clobbering the rest.
  useEffect(() => {
    if (!enabled || !parent) {
      return
    }
    if (getValues('registry_id') === null) {
      setValue('registry_id', parent.registry_id, { shouldDirty: true })
    }
    if (getValues('referent_id') === null) {
      setValue('referent_id', parent.referent_id, { shouldDirty: true })
    }
    if (getValues('opportunity_id') === null) {
      setValue('opportunity_id', parent.opportunity_id, { shouldDirty: true })
    }
    if (getValues('work_order_id') === null) {
      setValue('work_order_id', parent.work_order_id, { shouldDirty: true })
    }
  }, [enabled, parent, getValues, setValue])

  return {
    parentDateRange: enabled && parent ? { start: parent.start_date, end: parent.end_date } : null,
    registry: enabled ? (parent?.registry ?? null) : null,
    referent: enabled ? (parent?.referent ?? null) : null,
    opportunity: enabled ? (parent?.opportunity ?? null) : null,
    workOrder: enabled ? workOrderRefOf(parent) : null,
  }
}
