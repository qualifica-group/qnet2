/**
 * Owns every non-render concern of the time entry create/edit form (spec
 * 0122 MT-F2): RHF/Zod wiring, default values, the D-5 cascade (Cliente ->
 * Opportunita'/Commessa, Commessa -> Cliente, Task -> locked title/links),
 * the AC-029 minutes-from-times computation and the create/update submit.
 */

import { useEffect, useMemo, useRef, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { fetchTask, taskDetailQueryKey } from '@/features/tasks/api'
import { computeTrackedMinutes } from '@/features/time-entries/time-entry-format'
import { getTodayDateKey } from '@/features/time-entries/time-entry-period'
import { workOrderRegistryOf } from '@/features/time-entries/form/time-entry-context-fields'
import {
  buildTimeEntrySchema,
  type TimeEntryFormValues,
} from '@/features/time-entries/form/time-entry-schema'
import {
  useCreateTimeEntry,
  useUpdateTimeEntry,
} from '@/features/time-entries/form/use-time-entry-mutations'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'
import type {
  CreateTimeEntryPayload,
  TimeEntry,
  UpdateTimeEntryPayload,
} from '@/features/time-entries/types'

/**
 * Server-side field names mapped back onto the form for 422 handling.
 * `user_id` is deliberately absent: it is never a form field (D-8 `manageAll`
 * target comes from `mode.userId`, set by the caller's own context), so a 422
 * on it has no field to land on and falls back to the generic server-error
 * banner instead.
 */
const SERVER_ERROR_FIELDS: Path<TimeEntryFormValues>[] = [
  'date',
  'title',
  'task_type_id',
  'start_time',
  'end_time',
  'minutes',
  'notes',
  'registry_id',
  'opportunity_id',
  'work_order_id',
  'task_id',
]

/**
 * Create with an optional target user (D-8 `manageAll`, `userId` from the
 * caller's own context — a selected team member's dashboard — never a picker
 * in this form) and an optional pre-filled date (D-13 "+" on a day card).
 */
export type TimeEntryFormMode =
  | { type: 'create'; userId?: number; defaultDate?: string }
  | { type: 'edit'; entry: TimeEntry }

interface UseTimeEntryFormArgs {
  mode: TimeEntryFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (entry: TimeEntry) => void
}

function createDefaults(defaultDate: string | undefined): TimeEntryFormValues {
  return {
    title: '',
    date: defaultDate ?? getTodayDateKey(),
    task_type_id: null,
    start_time: null,
    end_time: null,
    minutes: null,
    notes: null,
    registry_id: null,
    opportunity_id: null,
    work_order_id: null,
    task_id: null,
  }
}

function editDefaults(entry: TimeEntry): TimeEntryFormValues {
  return {
    title: entry.title,
    date: entry.date,
    task_type_id: entry.task_type.id,
    start_time: entry.start_time,
    end_time: entry.end_time,
    minutes: entry.minutes,
    notes: entry.notes,
    registry_id: entry.registry?.id ?? null,
    opportunity_id: entry.opportunity?.id ?? null,
    work_order_id: entry.work_order?.id ?? null,
    task_id: entry.task?.id ?? null,
  }
}

/** `undefined` drops the key from the JSON body instead of sending an explicit `null`/`""`. */
function optionalTime(value: string | null): string | undefined {
  return value ?? undefined
}

/**
 * Builds the wire payload from form values. With a task linked (D-5), the
 * title/links are OMITTED rather than sent stale: the server derives and
 * imposes them from the task regardless, and the editor never even shows the
 * form's own values for those fields while locked (see `time-entry-editor`).
 */
function buildPayload(values: TimeEntryFormValues): UpdateTimeEntryPayload {
  const isTaskLinked = values.task_id !== null

  return {
    date: values.date,
    title: isTaskLinked ? undefined : values.title.trim(),
    task_type_id: values.task_type_id as number,
    start_time: optionalTime(values.start_time),
    end_time: optionalTime(values.end_time),
    minutes: values.minutes as number,
    notes: values.notes && values.notes.trim() !== '' ? values.notes : null,
    registry_id: isTaskLinked ? undefined : values.registry_id,
    opportunity_id: isTaskLinked ? undefined : values.opportunity_id,
    work_order_id: isTaskLinked ? undefined : values.work_order_id,
    task_id: values.task_id,
  }
}

export function useTimeEntryForm({ mode, onSuccess }: UseTimeEntryFormArgs) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const isEdit = mode.type === 'edit'

  const defaultValues = useMemo<TimeEntryFormValues>(
    () => (mode.type === 'edit' ? editDefaults(mode.entry) : createDefaults(mode.defaultDate)),
    [mode],
  )

  // Stable indirection (mirrors `useTaskForm`): `useForm` gets a resolver whose
  // identity never changes but which always runs the latest schema.
  const resolverRef = useRef<Resolver<TimeEntryFormValues>>(zodResolver(buildTimeEntrySchema(t)))
  const form = useForm<TimeEntryFormValues>({ resolver: (...args) => resolverRef.current(...args), defaultValues })
  const schema = useMemo(() => buildTimeEntrySchema(t), [t])
  useEffect(() => {
    resolverRef.current = zodResolver(schema)
  }, [schema])

  const taskId = useWatch({ control: form.control, name: 'task_id' })
  const isTaskLinked = taskId !== null

  // D-5: with a task linked, title/registry/opportunity/work_order are READ
  // from the task's own detail, not from the form's own (possibly stale or
  // never-filled) values — the server does the same on submit.
  const taskQuery = useQuery<TaskDetailWithPermissions>({
    queryKey: taskDetailQueryKey(taskId ?? -1),
    queryFn: () => fetchTask(taskId as number),
    enabled: isTaskLinked,
  })

  // AC-031: the three link pickers need their OWN RHF value set to the task's
  // id before they can display its label (`AsyncPaginatedSelect` resolves the
  // trigger from `value`, an override-only `selected` prop is not enough).
  // `buildPayload` still omits them while linked (D-5: the server ignores
  // whatever is sent), so this sync is display-only, never part of the wire
  // payload. Unlinking the task does NOT restore whatever the user typed
  // before linking it — an accepted simplification, not exercised by AC-031.
  useEffect(() => {
    if (!isTaskLinked || !taskQuery.data) {
      return
    }
    const linkedTask = taskQuery.data
    form.setValue('registry_id', linkedTask.registry_id, { shouldDirty: false })
    form.setValue('opportunity_id', linkedTask.opportunity_id, { shouldDirty: false })
    form.setValue('work_order_id', linkedTask.work_order_id, { shouldDirty: false })
  }, [isTaskLinked, taskQuery.data, form])

  // D-5: choosing a Cliente clears both dependent links — it may no longer
  // match either.
  const handleRegistryChange = () => {
    form.setValue('opportunity_id', null, { shouldDirty: true })
    form.setValue('work_order_id', null, { shouldDirty: true })
  }

  // D-5: Opportunita' and Commessa are mutually exclusive — picking one clears
  // the other. Clearing the picker (item === null) leaves the sibling alone.
  const handleOpportunityItemChange = (item: ForSelectItem | null) => {
    if (item) {
      form.setValue('work_order_id', null, { shouldDirty: true })
    }
  }

  // D-5/AC-031: picking a Commessa clears Opportunita' and adopts its own
  // Cliente (`WorkOrderForSelectResource::meta.registry`, resolved server-side
  // via `quote.opportunity.registry`) when the chain resolves one.
  const handleWorkOrderItemChange = (item: ForSelectItem | null) => {
    if (!item) {
      return
    }
    form.setValue('opportunity_id', null, { shouldDirty: true })
    const registry = workOrderRegistryOf(item)
    if (registry) {
      form.setValue('registry_id', registry.id, { shouldDirty: true })
    }
  }

  // AC-029: recompute minutes from the two times; a `null` result (missing
  // side, or end <= start) leaves whatever is already in "Tempo" untouched —
  // that IS the manual override surviving until a time actually changes.
  const recomputeMinutes = (startTime: string | null, endTime: string | null) => {
    const computed = computeTrackedMinutes(startTime, endTime)
    if (computed !== null) {
      form.setValue('minutes', computed, { shouldDirty: true })
    }
  }

  const handleStartTimeChange = (value: string) => {
    const nextStart = value || null
    form.setValue('start_time', nextStart, { shouldDirty: true })
    recomputeMinutes(nextStart, form.getValues('end_time'))
  }

  const handleEndTimeChange = (value: string) => {
    const nextEnd = value || null
    form.setValue('end_time', nextEnd, { shouldDirty: true })
    recomputeMinutes(form.getValues('start_time'), nextEnd)
  }

  const createMutation = useCreateTimeEntry()
  const updateMutation = useUpdateTimeEntry()
  const isSubmitting = createMutation.isPending || updateMutation.isPending

  const onSubmit = async (values: TimeEntryFormValues) => {
    setServerError(null)
    try {
      const payload = buildPayload(values)
      const saved =
        mode.type === 'edit'
          ? await updateMutation.mutateAsync({ id: mode.entry.id, payload })
          : await createMutation.mutateAsync(
              (mode.userId ? { ...payload, user_id: mode.userId } : payload) as CreateTimeEntryPayload,
            )
      onSuccess(saved)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(t('timeEntries.form.genericError'))
      }
    }
  }

  const resetToDefaults = () => {
    form.reset(defaultValues)
    setServerError(null)
  }

  return {
    form,
    isEdit,
    isTaskLinked,
    taskQuery,
    serverError,
    isSubmitting,
    onSubmit: form.handleSubmit(onSubmit),
    handleRegistryChange,
    handleOpportunityItemChange,
    handleWorkOrderItemChange,
    handleStartTimeChange,
    handleEndTimeChange,
    resetToDefaults,
  }
}
