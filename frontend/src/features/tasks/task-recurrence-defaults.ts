import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskRecurrenceDetail } from '@/features/tasks/types'

/** Stable module-level default: a fresh `[]` per render would break dependency stability. */
const EMPTY_WEEKDAYS: number[] = []

/** The "no recurrence" resting shape of the form's `recurrence` slice (spec 0120 D-1). */
export function emptyRecurrenceDefaults(): TaskFormValues['recurrence'] {
  return {
    enabled: false,
    frequency: null,
    interval: 1,
    weekdays: EMPTY_WEEKDAYS,
    month_day: null,
    ends: null,
    ends_on: null,
    occurrence_count: null,
  }
}

/** Hydrates the form's `recurrence` slice from the persisted series, or the empty shape above. */
export function recurrenceDefaults(detail: TaskRecurrenceDetail | null): TaskFormValues['recurrence'] {
  if (!detail) {
    return emptyRecurrenceDefaults()
  }
  return {
    enabled: true,
    frequency: detail.frequency,
    interval: detail.interval,
    weekdays: detail.weekdays ?? EMPTY_WEEKDAYS,
    month_day: detail.month_day,
    ends: detail.ends,
    ends_on: detail.ends_on,
    occurrence_count: detail.occurrence_count,
  }
}
