import { useEffect, useMemo, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createTask, taskDetailQueryKey, updateTask } from '@/features/tasks/api'
import { taskStatusMetaOf, type TaskStatusForSelectMeta } from '@/features/tasks/for-select-api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/tasks/task-form-payload'
import { buildTaskSchema, type TaskFormValues } from '@/features/tasks/task-schema'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TaskDetail, TaskFormMode } from '@/features/tasks/types'

/** Server-side field names mapped back onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'title',
  'task_status_id',
  'description',
  'registry_id',
  'referent_id',
  'parent_task_id',
  'task_type_id',
  'task_priority_id',
  'task_importance_id',
  'task_category_id',
  'opportunity_id',
  'work_order_id',
  'requester_id',
  'start_date',
  'end_date',
  'completion_date',
  'start_time',
  'end_time',
  'estimated_minutes',
  'is_blocked',
  'requires_closure_feedback',
  'closure_feedback',
  'assignee_ids',
  'watcher_ids',
] as const

/** Stable module-level default: a fresh `[]` per render would break dependency stability. */
const EMPTY_IDS: number[] = []

interface UseTaskFormArgs {
  mode: TaskFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (task: TaskDetail) => void
}

/** Default values of a brand-new task, with the "crea sotto-task" parent prefill (AC-085). */
function createDefaults(parentTaskId: number | null): TaskFormValues {
  return {
    title: '',
    task_status_id: null,
    description: null,
    registry_id: null,
    referent_id: null,
    parent_task_id: parentTaskId,
    task_type_id: null,
    task_priority_id: null,
    task_importance_id: null,
    task_category_id: null,
    opportunity_id: null,
    work_order_id: null,
    requester_id: null,
    start_date: null,
    end_date: null,
    completion_date: null,
    start_time: null,
    end_time: null,
    estimated_minutes: null,
    is_blocked: false,
    requires_closure_feedback: false,
    closure_feedback: null,
    assignee_ids: EMPTY_IDS,
    watcher_ids: EMPTY_IDS,
  }
}

/** Default values hydrated from the persisted task (edit mode). */
function editDefaults(task: TaskDetail): TaskFormValues {
  return {
    title: task.title,
    task_status_id: task.task_status_id,
    description: task.description,
    registry_id: task.registry_id,
    referent_id: task.referent_id,
    parent_task_id: task.parent_task_id,
    task_type_id: task.task_type_id,
    task_priority_id: task.task_priority_id,
    task_importance_id: task.task_importance_id,
    task_category_id: task.task_category_id,
    opportunity_id: task.opportunity_id,
    work_order_id: task.work_order_id,
    requester_id: task.requester_id,
    start_date: task.start_date,
    end_date: task.end_date,
    completion_date: task.completion_date,
    start_time: task.start_time,
    end_time: task.end_time,
    estimated_minutes: task.estimated_minutes,
    is_blocked: task.is_blocked,
    requires_closure_feedback: task.requires_closure_feedback,
    closure_feedback: task.closure_feedback,
    assignee_ids: task.assignees.map((user) => user.id),
    watcher_ids: task.watchers.map((user) => user.id),
  }
}

/**
 * The persisted status projected into the picker's own `meta` shape, so both
 * sources read alike. `TaskResource.statusRef()` projects `group` precisely so
 * an edit form can tell it is already in a closing phase before the user
 * touches the picker — something `system_key` alone can no longer answer.
 */
function persistedStatusMeta(mode: TaskFormMode): TaskStatusForSelectMeta | null {
  if (mode.type !== 'edit') {
    return null
  }
  const { task_status: status } = mode.task
  return {
    system_key: status.system_key,
    group: status.group,
    completion_percentage: status.completion_percentage,
    color: status.color,
    icon: status.icon,
  }
}

/**
 * Owns every non-render concern of `TaskFormBody`: RHF/Zod wiring, default
 * values, the AC-081 anagrafica -> referente reset, the AC-084 derived
 * percentage, the D-7 closure-feedback rule and the create/update submit.
 */
export function useTaskForm({ mode, onSuccess }: UseTaskFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const defaultValues = useMemo<TaskFormValues>(
    () => (mode.type === 'edit' ? editDefaults(mode.task) : createDefaults(mode.parentTaskId ?? null)),
    [mode],
  )

  /**
   * The picked status' presentation bag. Client UI state, not server state:
   * it is written from the picker's `onItemChange` — a direct consequence of
   * the user's selection — never from a render-time effect that could
   * overwrite a later choice. Seeded from the persisted status so an edit
   * form shows the right percentage before the user touches anything.
   */
  const [statusMeta, setStatusMeta] = useState<TaskStatusForSelectMeta | null>(() =>
    persistedStatusMeta(mode),
  )

  // Stable indirection (mirrors `useWorkOrderForm`): `useForm` gets a resolver
  // whose identity never changes but which always runs the latest schema —
  // the D-7 rule depends on the picked status' `group`, which is not a form
  // value.
  const resolverRef = useRef<Resolver<TaskFormValues>>(zodResolver(buildTaskSchema(t)))

  const form = useForm<TaskFormValues>({
    resolver: (values, context, options) => resolverRef.current(values, context, options),
    defaultValues,
  })

  const schema = useMemo(
    () => buildTaskSchema(t, statusMeta?.group ?? null),
    [t, statusMeta?.group],
  )

  useEffect(() => {
    resolverRef.current = zodResolver(schema)
  }, [schema])

  // AC-081: the referent is anagrafica-scoped (BR-4 of the same pattern used
  // by Opportunita'), so a referent from the previous registry is no longer
  // valid. Reset EXPLICITLY here, in the change handler — never in an effect.
  const handleRegistryChange = () => {
    form.setValue('referent_id', null, { shouldDirty: true })
  }

  // AC-084: choosing another status re-derives the read-only percentage, and
  // (D-7) re-arms the closure-feedback rule, without any write to the form.
  const handleStatusItemChange = (item: ForSelectItem | null) => {
    setStatusMeta(taskStatusMetaOf(item))
  }

  const onSubmit = async (values: TaskFormValues) => {
    setServerError(null)
    const errorFields: Path<TaskFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateTask(mode.task.id, buildUpdatePayload(values, mode.task))
        queryClient.setQueryData(taskDetailQueryKey(mode.task.id), saved)
        toast.success(t('tasks.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createTask(buildCreatePayload(values))
      toast.success(t('tasks.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('tasks.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    onSubmit,
    handleRegistryChange,
    handleStatusItemChange,
    /** Derived, read-only, never submitted (D-6/AC-084); `null` while no status is picked. */
    completionPercentage: statusMeta?.completion_percentage ?? null,
    /** The picked status' PHASE: drives the D-7 client rule and the closure section. */
    statusGroup: statusMeta?.group ?? null,
  }
}
