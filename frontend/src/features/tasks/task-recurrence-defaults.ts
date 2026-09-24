import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskRecurrenceDetail } from '@/features/tasks/types'

/** Stable module-level default: a fresh `[]` per render would break dependency stability. */
const EMPTY_WEEKDAYS: number[] = []

/** The "no recurrence" resting shape of the form's `recurrence` slice (spec 0120 D-1, spec 0155 D-1). */
export function emptyRecurrenceDefaults(): TaskFormValues['recurrence'] {
  return {
    enabled: false,
    frequency: null,
    interval: 1,
    weekdays: EMPTY_WEEKDAYS,
    month_mode: null,
    month_day: null,
    ordinal: null,
    ordinal_weekday: null,
    year_month: null,
    workdays_only: false,
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
    month_mode: detail.month_mode,
    month_day: detail.month_day,
    ordinal: detail.ordinal,
    ordinal_weekday: detail.ordinal_weekday,
    year_month: detail.year_month,
    workdays_only: detail.workdays_only,
    ends: detail.ends,
    ends_on: detail.ends_on,
    occurrence_count: detail.occurrence_count,
  }
}
