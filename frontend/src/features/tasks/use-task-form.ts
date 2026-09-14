import { useEffect, useMemo, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { useAuth } from '@/features/auth/use-auth'
import { uploadAttachment } from '@/features/attachments/api'
import { DOCUMENTS_COLLECTION } from '@/features/attachments/types'
import { createTask, taskDetailQueryKey, TASK_ATTACHABLE_ALIAS, updateTask } from '@/features/tasks/api'
import { taskStatusMetaOf, type TaskStatusForSelectMeta } from '@/features/tasks/for-select-api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/tasks/task-form-payload'
import { buildTaskSchema, type TaskFormValues } from '@/features/tasks/task-schema'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TaskDetail, TaskFormMode } from '@/features/tasks/types'

/** Server-side field names mapped back onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'title',
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
  'requires_closure_feedback',
  'requires_validation',
  'assignee_ids',
  'watcher_ids',
] as const

/**
 * AC-022: a 422 on either of these has nowhere to land on this form —
 * `closure_feedback` left it entirely (spec 0121 D-7), and `task_status_id`'s
 * refusal here is `TaskValidationRequirementGuard` (D-5), a workflow rule the
 * status picker cannot express, not a picker-level validation. Both surface
 * as a toast with the server's own message instead of a field error.
 */
const TOAST_ONLY_SERVER_ERROR_FIELDS = ['closure_feedback', 'task_status_id'] as const

/** The first message the server attached to `field` in a 422 response, or `null`. */
function serverFieldMessage(error: unknown, field: string): string | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return null
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  return errors?.[field]?.[0] ?? null
}

/** Stable module-level default: a fresh `[]` per render would break dependency stability. */
const EMPTY_IDS: number[] = []

interface UseTaskFormArgs {
  mode: TaskFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (task: TaskDetail) => void
}

/**
 * Default values of a brand-new task, with the "crea sotto-task" parent
 * prefill (AC-085) and the requester defaulted to the actor creating it
 * (spec 0118 D-1: `requester_id` is now required, and the actor is the
 * requester in the overwhelming majority of cases) — left modifiable, never
 * locked.
 */
function createDefaults(parentTaskId: number | null, requesterId: number | null): TaskFormValues {
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
    requester_id: requesterId,
    start_date: null,
    end_date: null,
    completion_date: null,
    start_time: null,
    end_time: null,
    estimated_minutes: null,
    requires_closure_feedback: false,
    requires_validation: false,
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
    requires_closure_feedback: task.requires_closure_feedback,
    requires_validation: task.requires_validation,
    assignee_ids: task.assignees.map((user) => user.id),
    watcher_ids: task.watchers.map((user) => user.id),
  }
}

/**
 * Uploads every staged file against the freshly created task, one request at
 * a time (spec 0118 D-7/AC-023) — mirrors `useAttachments`' own sequential
 * upload: the endpoint takes one file per request, and a burst of parallel
 * multipart bodies is what trips server upload limits. Never throws: a
 * rejected file is reported back by name so the caller can still navigate
 * (D-8) instead of trapping the user on a form for an already-saved task.
 */
async function uploadStagedAttachments(taskId: number, files: File[]): Promise<string[]> {
  const failed: string[] = []
  for (const file of files) {
    try {
      await uploadAttachment({
        resource: TASK_ATTACHABLE_ALIAS,
        id: taskId,
        collection: DOCUMENTS_COLLECTION,
        file,
      })
    } catch {
      failed.push(file.name)
    }
  }
  return failed
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
  const { user } = useAuth()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'
  /** The connected actor projected onto the picker's hydration shape (D-1 requester prefill). */
  const currentUserRef: RelationFieldRef | null = user ? { id: user.id, name: user.name } : null

  const defaultValues = useMemo<TaskFormValues>(
    () =>
      mode.type === 'edit'
        ? editDefaults(mode.task)
        : createDefaults(mode.parentTaskId ?? null, user?.id ?? null),
    [mode, user?.id],
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

  /**
   * Files chosen before the task exists (spec 0118 D-7): pure in-memory
   * staging, never uploaded from here. `TaskFormBody` mounts `<TaskAttachmentStaging>`
   * on create only (AC-027) — edit mode never touches this state.
   */
  const [stagedAttachments, setStagedAttachments] = useState<File[]>([])

  const addStagedAttachments = (files: File[]) => {
    setStagedAttachments((current) => [...current, ...files])
  }

  const removeStagedAttachment = (index: number) => {
    setStagedAttachments((current) => current.filter((_file, fileIndex) => fileIndex !== index))
  }

  // Stable indirection (mirrors `useWorkOrderForm`): `useForm` gets a resolver
  // whose identity never changes but which always runs the latest schema.
  const resolverRef = useRef<Resolver<TaskFormValues>>(zodResolver(buildTaskSchema(t, !isEdit)))

  const form = useForm<TaskFormValues>({
    resolver: (values, context, options) => resolverRef.current(values, context, options),
    defaultValues,
  })

  const schema = useMemo(() => buildTaskSchema(t, !isEdit), [t, isEdit])

  useEffect(() => {
    resolverRef.current = zodResolver(schema)
  }, [schema])

  // AC-081: the referent is anagrafica-scoped (BR-4 of the same pattern used
  // by Opportunita'), so a referent from the previous registry is no longer
  // valid. Reset EXPLICITLY here, in the change handler — never in an effect.
  const handleRegistryChange = () => {
    form.setValue('referent_id', null, { shouldDirty: true })
  }

  // AC-084: choosing another status re-derives the read-only percentage,
  // without any write to the form.
  const handleStatusItemChange = (item: ForSelectItem | null) => {
    setStatusMeta(taskStatusMetaOf(item))
  }

  const onSubmit = async (values: TaskFormValues) => {
    setServerError(null)
    const errorFields: Path<TaskFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        // Step 1 (edit): PATCH and refresh the cached detail.
        const saved = await updateTask(mode.task.id, buildUpdatePayload(values, mode.task))
        queryClient.setQueryData(taskDetailQueryKey(mode.task.id), saved)
        toast.success(t('tasks.form.updated'))
        onSuccess(saved)
        return
      }

      // Step 1 (create): POST the task once — `task_status_id` is derived
      // server-side from the assignees (D-3/D-4), never sent here.
      const created = await createTask(buildCreatePayload(values))

      // Step 2: upload whatever is still in staging, one request per file
      // (D-7/AC-023). No staged file, no call at all (AC-025).
      if (stagedAttachments.length > 0) {
        const failed = await uploadStagedAttachments(created.id, stagedAttachments)
        if (failed.length > 0) {
          // D-8: the task IS saved; a failed attachment never rolls it back
          // and never re-traps the user on the form — only names the misses.
          toast.error(t('tasks.form.attachments.uploadFailed', { files: failed.join(', ') }))
        }
      }

      // Step 3: report success and hand off to the caller (navigates to the
      // detail, AC-024) — after the uploads have settled, not before.
      toast.success(t('tasks.form.created'))
      onSuccess(created)
    } catch (error) {
      // AC-022: `closure_feedback`/`task_status_id` have no field to land on
      // any more (D-5/D-7) — surface the server's own message as a toast
      // before falling back to the normal field mapping.
      const toastMessage = TOAST_ONLY_SERVER_ERROR_FIELDS.map((field) =>
        serverFieldMessage(error, field),
      ).find((message): message is string => message !== null)
      if (toastMessage) {
        toast.error(toastMessage)
        return
      }
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
    /** The connected actor's picker hydration, for the requester prefill (D-1). */
    currentUserRef,
    /** In-memory files staged for upload right after a successful create (spec 0118 D-7). */
    stagedAttachments,
    addStagedAttachments,
    removeStagedAttachment,
  }
}
