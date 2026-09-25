/**
 * Owns the "Segnatempo" section's non-render concerns inside the Completa
 * pop-up (spec 0123 D-1/D-3, AC-039/AC-040). A SEPARATE `useForm` from the
 * dialog's own (`closure_feedback`/`validation_status_id`): `TimeEntryEditor`
 * (`time-entries/form/`) wires its `MetaField`s to fixed top-level names
 * (`date`, `minutes`, …), so nesting them under one shared `time_entry` key
 * would mean re-parametrizing that shared component's every field for a need
 * no other caller has. Reuses `buildTaskTimeEntrySchema`
 * (`time-entries/task/`) untouched (D-3: one source of truth for the rules)
 * and the SAME default resolution `useTaskTimeEntryForm` applies to the
 * Task-embedded "Nuovo intervallo" editor, mirrored here for the type default
 * only (AC-039: the task's own type when still active, otherwise the first
 * option).
 *
 * Spec 0162 D-3/D-4: `requiresTimeEntry: false` additionally exposes
 * `enabled`/`setEnabled` (the "Registra il tempo" switch's own state, ON by
 * default) and makes `validate()` return `undefined` — a THIRD outcome next
 * to the payload and `null` (invalid) — when the caller turned it off: no
 * payload, no error, the section simply plays no part in the submit.
 */

import { useEffect, useMemo, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path, Resolver, UseFormSetError } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { computeTrackedMinutes } from '@/features/time-entries/time-entry-format'
import { getTodayDateKey } from '@/features/time-entries/time-entry-period'
import {
  buildTaskTimeEntrySchema,
  type TaskTimeEntryFormValues,
} from '@/features/time-entries/task/task-time-entry-schema'
import { useTimeEntryTypeOptions } from '@/features/time-entries/form/time-entry-type-picker'
import type { TaskTypeForSelectItem } from '@/features/task-types/for-select-api'
import type { CompleteTaskTimeEntryPayload } from '@/features/tasks/types'

/** `time_entry.*` server field keys the `/complete` 422 carries (D-1/D-3). */
const TIME_ENTRY_SERVER_ERROR_FIELDS: Path<TaskTimeEntryFormValues>[] = [
  'date',
  'task_type_id',
  'minutes',
  'start_time',
  'end_time',
  'notes',
]

function createDefaults(defaultTaskTypeId: number | null): TaskTimeEntryFormValues {
  return {
    title: '',
    date: getTodayDateKey(),
    task_type_id: defaultTaskTypeId,
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

/** AC-039: the task's own type while it is still an active option, otherwise the picker's first one. */
function resolveDefaultTaskTypeId(taskTypeId: number | null, options: TaskTypeForSelectItem[]): number | null {
  if (taskTypeId !== null && options.some((option) => option.id === taskTypeId)) {
    return taskTypeId
  }
  return options[0]?.id ?? null
}

function buildPayload(values: TaskTimeEntryFormValues): CompleteTaskTimeEntryPayload {
  return {
    date: values.date,
    task_type_id: values.task_type_id as number,
    minutes: values.minutes as number,
    start_time: values.start_time,
    end_time: values.end_time,
    notes: values.notes,
  }
}

/**
 * Strips the `time_entry.` prefix `/complete`'s 422 carries before wiring a
 * message onto this section's own field — a sibling of the shared
 * `applyServerValidationErrors` (`auth/form-errors.ts`), which cannot express
 * that prefix since its field list IS the server's own key.
 */
export function applyTimeEntryServerErrors(
  error: unknown,
  setError: UseFormSetError<TaskTimeEntryFormValues>,
): boolean {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return false
  }

  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  let matched = false
  for (const field of TIME_ENTRY_SERVER_ERROR_FIELDS) {
    const message = errors?.[`time_entry.${field}`]?.[0]
    if (message) {
      setError(field, { message })
      matched = true
    }
  }
  return matched
}

interface UseTaskCompleteTimeEntryFormArgs {
  /** `task.task_type_id`; `null` when the task carries none (AC-039 then defaults to the first option). */
  taskTypeId: number | null
  /**
   * Spec 0162 D-2/D-3: the task's own `requires_time_entry` (single dialog)
   * or "at least one selected task requires it" (bulk dialog, D-4). Defaults
   * to `true` — today's always-mandatory behaviour — for callers that predate
   * this flag.
   */
  requiresTimeEntry?: boolean
}

export function useTaskCompleteTimeEntryForm({
  taskTypeId,
  requiresTimeEntry = true,
}: UseTaskCompleteTimeEntryFormArgs) {
  const { t } = useTranslation()
  const { options } = useTimeEntryTypeOptions()
  const defaultTaskTypeId = resolveDefaultTaskTypeId(taskTypeId, options)
  const [enabled, setEnabled] = useState(true)

  const schema = useMemo(() => buildTaskTimeEntrySchema(t), [t])
  const resolverRef = useRef<Resolver<TaskTimeEntryFormValues>>(zodResolver(schema))
  useEffect(() => {
    resolverRef.current = zodResolver(schema)
  }, [schema])

  const form = useForm<TaskTimeEntryFormValues>({
    resolver: (...args) => resolverRef.current(...args),
    defaultValues: createDefaults(defaultTaskTypeId),
  })

  // Mirrors `useTaskTimeEntryForm`'s own "first available type" reset: the
  // type options load asynchronously, so the default is re-applied once they
  // arrive, as long as the field is still untouched.
  useEffect(() => {
    if (defaultTaskTypeId === null) {
      return
    }
    if (form.getValues('task_type_id') === null && !form.formState.isDirty) {
      form.setValue('task_type_id', defaultTaskTypeId)
    }
  }, [defaultTaskTypeId, form])

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

  /**
   * AC-039: validates the section and returns the wire payload, `null` while
   * it stays invalid (submit blocked, no API call), or — spec 0162 D-3/D-4,
   * `requiresTimeEntry: false` with the switch turned off — `undefined`: the
   * section is skipped outright, no validation runs, no payload is sent.
   */
  const validate = async (): Promise<CompleteTaskTimeEntryPayload | null | undefined> => {
    if (!requiresTimeEntry && !enabled) {
      return undefined
    }
    const isValid = await form.trigger()
    return isValid ? buildPayload(form.getValues()) : null
  }

  const reset = () => {
    form.reset(createDefaults(defaultTaskTypeId))
    setEnabled(true)
  }

  return {
    form,
    /** Spec 0162 D-3/D-4: `true` when the section is a MANDATORY part of the submit — the dialog renders no switch then. */
    optional: !requiresTimeEntry,
    enabled,
    setEnabled,
    handleStartTimeChange,
    handleEndTimeChange,
    validate,
    applyServerErrors: (error: unknown) => applyTimeEntryServerErrors(error, form.setError),
    reset,
  }
}
