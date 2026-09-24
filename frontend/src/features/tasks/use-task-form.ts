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
import { buildCreatePayload, buildUpdatePayload } from '@/features/tasks/task-form-payload'
import {
  SERVER_ERROR_FIELDS,
  serverFieldMessage,
  TOAST_ONLY_SERVER_ERROR_FIELDS,
  workOrderStageConflictMessage,
} from '@/features/tasks/task-form-server-error-fields'
import { emptyRecurrenceDefaults, recurrenceDefaults } from '@/features/tasks/task-recurrence-defaults'
import { buildTaskSchema, type TaskFormValues } from '@/features/tasks/task-schema'
import { resolveWorkOrderStagePrefill, useTaskFormStageHandlers } from '@/features/tasks/use-task-form-stage-handlers'
import { useTaskLookupDefaults } from '@/features/tasks/use-task-lookup-defaults'
import { useTaskParentPrefill } from '@/features/tasks/use-task-parent-prefill'
import { useTaskWorkOrderPrefill } from '@/features/tasks/use-task-work-order-prefill'
import { useTaskWorkOrderRegistryId } from '@/features/tasks/use-task-work-order-registry'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TaskDetail, TaskFormMode } from '@/features/tasks/types'

/** Stable module-level default: a fresh `[]` per render would break dependency stability. */
const EMPTY_IDS: number[] = []

/** Today as `YYYY-MM-DD` in the ACTOR's own local calendar day (not UTC: a `Y-m-d` due date compares as a plain string). */
function todayIsoDate(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

interface UseTaskFormArgs {
  mode: TaskFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (task: TaskDetail) => void
}

/**
 * Default values of a brand-new task, with the "crea sotto-task" parent
 * prefill (AC-085), the work order prefill of the Commessa detail's Task tab
 * (spec 0133 D-4) and the requester defaulted to the actor creating it
 * (spec 0118 D-1: `requester_id` is now required, and the actor is the
 * requester in the overwhelming majority of cases) — left modifiable, never
 * locked.
 */
function createDefaults(
  parentTaskId: number | null,
  workOrderId: number | null,
  workOrderStageId: number | null,
  requesterId: number | null,
): TaskFormValues {
  // Spec 0154 D-9: today, UNLESS this create is opened as "crea sotto-task"
  // or from a commessa's Task tab — both contexts may eventually prefill
  // their own date range/end date, so this default steps aside for them.
  const hasLinkContext = parentTaskId !== null || workOrderId !== null
  return {
    title: '',
    task_status_id: null,
    description: null,
    is_private: false,
    evidence: null,
    registry_id: null,
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
    end_date: hasLinkContext ? null : todayIsoDate(),
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
  }
}

/** Default values hydrated from the persisted task (edit mode). */
function editDefaults(task: TaskDetail): TaskFormValues {
  return {
    title: task.title,
    task_status_id: task.task_status_id,
    description: task.description,
    is_private: task.is_private,
    evidence: task.evidence,
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

  const defaultValues = useMemo<TaskFormValues>(
    () =>
      mode.type === 'edit'
        ? editDefaults(mode.task)
        : createDefaults(
            mode.parentTaskId ?? null,
            mode.workOrderId ?? null,
            mode.workOrderStageId ?? null,
            user?.id ?? null,
          ),
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
