import { useEffect, useMemo, useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { isPayloadTooLargeError } from '@/components/rich-text/rich-text-errors'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { useAuth } from '@/features/auth/use-auth'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { createTask, taskDetailQueryKey, updateTask } from '@/features/tasks/api'
import { taskStatusMetaOf, type TaskStatusForSelectMeta } from '@/features/tasks/for-select-api'
import { uploadStagedAttachments } from '@/features/tasks/task-form-attachments-upload'
import { createDefaults, duplicateDefaults, editDefaults } from '@/features/tasks/task-form-defaults'
import { buildCreatePayload, buildUpdatePayload } from '@/features/tasks/task-form-payload'
import {
  SERVER_ERROR_FIELDS,
  serverFieldMessage,
  TOAST_ONLY_SERVER_ERROR_FIELDS,
  workOrderStageConflictMessage,
} from '@/features/tasks/task-form-server-error-fields'
import { buildTaskSchema, type TaskFormValues } from '@/features/tasks/task-schema'
import { applySubtaskServerErrors } from '@/features/tasks/task-subtask-rows'
import { useTaskFormStageHandlers } from '@/features/tasks/use-task-form-stage-handlers'
import { useTaskLookupDefaults } from '@/features/tasks/use-task-lookup-defaults'
import { useTaskParentPrefill } from '@/features/tasks/use-task-parent-prefill'
import { useTaskWorkOrderPrefill } from '@/features/tasks/use-task-work-order-prefill'
import { useTaskWorkOrderRegistryId } from '@/features/tasks/use-task-work-order-registry'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TaskDetail, TaskFormMode } from '@/features/tasks/types'

interface UseTaskFormArgs {
  mode: TaskFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (task: TaskDetail) => void
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
  const { field: fieldPermission } = useResourcePermissions()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'
  // Spec 0120 D-12: `recurrence` is ONE protected field gating the whole
  // section (`TaskRecurrenceSection` reads the same key). Read once here so
  // the payload builders never send a key the actor could not have touched.
  const recurrencePermission = fieldPermission('recurrence')
  const canEditRecurrence = recurrencePermission.editable && !recurrencePermission.disabled
  /** The connected actor projected onto the picker's hydration shape (D-1 requester prefill). */
  const currentUserRef: RelationFieldRef | null = user ? { id: user.id, name: user.name } : null

  const defaultValues = useMemo<TaskFormValues>(() => {
    if (mode.type === 'edit') {
      return editDefaults(mode.task)
    }
    if (mode.type === 'duplicate') {
      return duplicateDefaults(mode.source, t('common.copySuffix'))
    }
    return createDefaults(
      mode.parentTaskId ?? null,
      mode.workOrderId ?? null,
      mode.workOrderStageId ?? null,
      user?.id ?? null,
      mode.taskStatusId ?? null,
      mode.endDate ?? null,
      mode.registryId ?? null,
    )
  }, [mode, user?.id, t])

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
  // Spec 0195: the task detail keeps this form mounted while a domain action
  // (Completa, Riapri...) changes the persisted status — re-seed on that
  // change, during render (React's "adjust state on prop change" pattern).
  const persistedStatusId = mode.type === 'edit' ? mode.task.task_status_id : null
  const [statusMetaSeedId, setStatusMetaSeedId] = useState(persistedStatusId)
  if (statusMetaSeedId !== persistedStatusId) {
    setStatusMetaSeedId(persistedStatusId)
    setStatusMeta(persistedStatusMeta(mode))
  }

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

  // Edit mode IS the task detail (spec 0195 D-2): the persisted task can change
  // under the form (a domain action, a subtask write), so `values` re-syncs it
  // while `keepDirtyValues` preserves what the user is still editing. Without
  // it the diff-based PATCH would send a stale untouched value back.
  const form = useForm<TaskFormValues>({
    resolver: (values, context, options) => resolverRef.current(values, context, options),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    // Scoped to that `values` re-sync: every explicit `reset` that means to
    // DROP an edit passes `keepDirtyValues: false` (RHF merges these options
    // into every reset, explicit ones winning).
    resetOptions: isEdit ? { keepDirtyValues: true } : undefined,
  })

  // Spec 0123 D-10/D-7: the parent's link fields prefill the empty ones, and
  // its own date range feeds the schema's mirror below — both create-only.
  const parentPrefill = useTaskParentPrefill({
    control: form.control,
    setValue: form.setValue,
    getValues: form.getValues,
    enabled: !isEdit,
  })

  const workOrderPrefillRef = useTaskWorkOrderPrefill(mode.type === 'create' ? (mode.workOrderId ?? null) : null)

  // Spec 0154 D-11: the currently linked commessa's OWN registry, resolved
  // independently of any picker the user has touched this session (works for
  // a persisted edit-mode value too) — the one signal `handleRegistryChange`
  // needs to decide whether the commessa survives an anagrafica change.
  const workOrderId = useWatch({ control: form.control, name: 'work_order_id' })
  const workOrderRegistryId = useTaskWorkOrderRegistryId(workOrderId)

  // Spec 0154 D-8: on create, precompile type/priority/importance from each
  // catalog's default row, exactly like the server would when the field is
  // left out of the payload.
  const lookupDefaults = useTaskLookupDefaults(!isEdit)
  useEffect(() => {
    if (isEdit) {
      return
    }
    if (lookupDefaults.taskTypeId !== null && form.getValues('task_type_id') === null) {
      form.setValue('task_type_id', lookupDefaults.taskTypeId, { shouldDirty: true })
    }
    if (lookupDefaults.taskPriorityId !== null && form.getValues('task_priority_id') === null) {
      form.setValue('task_priority_id', lookupDefaults.taskPriorityId, { shouldDirty: true })
    }
    if (lookupDefaults.taskImportanceId !== null && form.getValues('task_importance_id') === null) {
      form.setValue('task_importance_id', lookupDefaults.taskImportanceId, { shouldDirty: true })
    }
  }, [isEdit, lookupDefaults, form])

  const schema = useMemo(
    () => buildTaskSchema(t, !isEdit, parentPrefill.parentDateRange),
    [t, isEdit, parentPrefill.parentDateRange],
  )

  useEffect(() => {
    resolverRef.current = zodResolver(schema)
  }, [schema])

  // AC-081/spec 0154 D-11: the referent, opportunity and lead are all
  // anagrafica-scoped, so a value from the previous registry is no longer
  // valid. The commessa survives ONLY when it belongs to the new registry
  // (an UNKNOWN commessa registry — still loading, or none linked at all —
  // never triggers a clear). Reset EXPLICITLY here, in the change handler —
  // never in an effect.
  const handleRegistryChange = (nextRegistryId: number | null) => {
    form.setValue('referent_id', null, { shouldDirty: true })
    form.setValue('opportunity_id', null, { shouldDirty: true })
    form.setValue('lead_id', null, { shouldDirty: true })
    if (workOrderRegistryId !== null && workOrderRegistryId !== nextRegistryId) {
      form.setValue('work_order_id', null, { shouldDirty: true })
      form.setValue('work_order_stage_id', null, { shouldDirty: true })
    }
  }

  // Spec 0146 D-3: the "Fase" field's two reset handlers, split out for file size.
  const { handleWorkOrderChange, handleParentChange } = useTaskFormStageHandlers(form.setValue)

  /**
   * Spec 0154 D-11: commessa and opportunita' are mutually exclusive — picking
   * a commessa clears the opportunita' and imposes the commessa's OWN
   * registry (from the for-select item's `meta.registry_id`, already on hand
   * from the pick itself, no extra request). Clearing the field (`item` is
   * `null`) touches nothing else.
   */
  const handleWorkOrderItemChange = (item: ForSelectItem | null) => {
    if (!item) {
      return
    }
    form.setValue('opportunity_id', null, { shouldDirty: true })
    const meta = (item as ForSelectItem & { meta?: { registry_id?: number | null } }).meta
    form.setValue('registry_id', meta?.registry_id ?? null, { shouldDirty: true })
  }

  /** Spec 0154 D-11: the reverse exclusion — picking an opportunita' clears the commessa and its fase. */
  const handleOpportunityChange = (nextOpportunityId: number | null) => {
    if (nextOpportunityId === null) {
      return
    }
    form.setValue('work_order_id', null, { shouldDirty: true })
    form.setValue('work_order_stage_id', null, { shouldDirty: true })
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
        const saved = await updateTask(
          mode.task.id,
          buildUpdatePayload(values, mode.task, canEditRecurrence),
        )
        queryClient.setQueryData(taskDetailQueryKey(mode.task.id), saved)
        // Step 2 (edit): the detail stays mounted on the saved task, clean.
        form.reset(editDefaults(saved), { keepDirtyValues: false })
        toast.success(t('tasks.form.updated'))
        onSuccess(saved)
        return
      }

      // Step 1 (create): POST the task once — `task_status_id` is derived
      // server-side from the assignees (D-3/D-4), never sent here.
      const created = await createTask(buildCreatePayload(values, canEditRecurrence))

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
      // Spec 0128 follow-up: the description's inline `data:` images can push
      // the request past the server's body limit — surfaced on `description`,
      // the only field that could have carried them. Form values (the typed
      // HTML included) are untouched: `setError` never resets the form.
      if (isPayloadTooLargeError(error)) {
        form.setError('description', { message: t('richText.errors.payloadTooLarge') })
        return
      }
      // Spec 0146 D-4/AC-015: a closed-fase 409 has no `errors` map — surface
      // the envelope's own message directly on the "Fase" field.
      const stageConflict = workOrderStageConflictMessage(error)
      if (stageConflict !== null) {
        form.setError('work_order_stage_id', { message: stageConflict })
        return
      }
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
      // Spec 0155 D-3: `subtasks.N.field` 422s address a dynamic row index —
      // handled separately from the fixed `errorFields` list below.
      if (mode.type !== 'edit' && applySubtaskServerErrors(error, form.setError)) {
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
    /** Drops a reported server error, for a caller that discards the edit it belonged to (spec 0195 inline edit). */
    clearServerError: () => setServerError(null),
    onSubmit,
    handleRegistryChange,
    handleWorkOrderChange,
    handleWorkOrderItemChange,
    handleOpportunityChange,
    handleParentChange,
    handleStatusItemChange,
    /** Derived, read-only, never submitted (D-6/AC-084); `null` while no status is picked. */
    completionPercentage: statusMeta?.completion_percentage ?? null,
    /** The connected actor's picker hydration, for the requester prefill (D-1). */
    currentUserRef,
    /** In-memory files staged for upload right after a successful create (spec 0118 D-7). */
    stagedAttachments,
    addStagedAttachments,
    removeStagedAttachment,
    /** The parent's own link refs (spec 0123 D-10), for the four pickers' `selected` hydration on create. */
    parentPrefillRefs: parentPrefill,
    /** The work order the create form was opened for (spec 0133 D-4), for the picker's `selected` hydration. */
    workOrderPrefillRef,
  }
}

/** What `useTaskForm` hands its callers: the form, its cascade handlers and the create-time extras. */
export type TaskFormState = ReturnType<typeof useTaskForm>
