/**
 * Owns the Task-embedded "Nuovo intervallo" editor's non-render concerns
 * (spec 0122 MT-F6, D-9): RHF/Zod wiring, the AC-029 minutes-from-times
 * computation, submit to the task-scoped endpoint, and the "reset to the
 * first available type" behaviour after a successful create. A dedicated
 * hook instead of reusing `useTimeEntryForm` (`form/`): that hook targets
 * the generic `/api/time-entries` endpoint and its title/links D-5 cascade,
 * neither of which applies here — `task_id` comes from the URL, never a
 * form field.
 */

import { useEffect, useMemo, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { computeTrackedMinutes } from '@/features/time-entries/time-entry-format'
import { getTodayDateKey } from '@/features/time-entries/time-entry-period'
import {
  buildTaskTimeEntrySchema,
  type TaskTimeEntryFormValues,
} from '@/features/time-entries/task/task-time-entry-schema'
import { useCreateTaskTimeEntry } from '@/features/time-entries/task/use-task-time-entry-mutations'
import type { CreateTaskTimeEntryPayload } from '@/features/time-entries/types'

const SERVER_ERROR_FIELDS: Path<TaskTimeEntryFormValues>[] = [
  'date',
  'task_type_id',
  'start_time',
  'end_time',
  'minutes',
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

/** Wire payload of `POST /api/tasks/{task}/time-entries`: no title/links (D-9). */
function buildPayload(values: TaskTimeEntryFormValues): CreateTaskTimeEntryPayload {
  return {
    date: values.date,
    task_type_id: values.task_type_id as number,
    start_time: values.start_time ?? undefined,
    end_time: values.end_time ?? undefined,
    minutes: values.minutes as number,
    notes: values.notes && values.notes.trim() !== '' ? values.notes : null,
  }
}

interface UseTaskTimeEntryFormArgs {
  taskId: number
  /** First option of the type picker, or `null` while it is still loading. */
  defaultTaskTypeId: number | null
}

export function useTaskTimeEntryForm({ taskId, defaultTaskTypeId }: UseTaskTimeEntryFormArgs) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)

  const defaultValues = useMemo(() => createDefaults(defaultTaskTypeId), [defaultTaskTypeId])
  const resolverRef = useRef<Resolver<TaskTimeEntryFormValues>>(zodResolver(buildTaskTimeEntrySchema(t)))
  const form = useForm<TaskTimeEntryFormValues>({
    resolver: (...args) => resolverRef.current(...args),
    defaultValues,
  })
  const schema = useMemo(() => buildTaskTimeEntrySchema(t), [t])
  useEffect(() => {
    resolverRef.current = zodResolver(schema)
  }, [schema])

  // The type options load asynchronously (`useTimeEntryTypeOptions`); once
  // the first one arrives, pick it as long as the field is still untouched
  // (mirrors the AC "tipo = primo disponibile" reset, applied on mount too).
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

  const createMutation = useCreateTaskTimeEntry(taskId)

  const onSubmit = async (values: TaskTimeEntryFormValues) => {
    setServerError(null)
    try {
      await createMutation.mutateAsync(buildPayload(values))
      form.reset(createDefaults(defaultTaskTypeId))
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_ERROR_FIELDS)) {
        setServerError(t('timeEntries.form.genericError'))
      }
    }
  }

  return {
    form,
    serverError,
    isSubmitting: createMutation.isPending,
    onSubmit: form.handleSubmit(onSubmit),
    handleStartTimeChange,
    handleEndTimeChange,
  }
}
