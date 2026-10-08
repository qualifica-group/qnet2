import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskRecurrenceRule } from '@/features/tasks/task-recurrence-format'

type RecurrenceFormValues = TaskFormValues['recurrence']

/** The day-of-month part a monthly/yearly rule needs: a fixed day, or an ordinal weekday (spec 0155 D-1). */
function hasMonthDay(values: RecurrenceFormValues): boolean {
  if (values.month_mode === 'ordinal') {
    return values.ordinal !== null && values.ordinal_weekday !== null
  }
  return values.month_day !== null
}

/** Whether the picked frequency has every field of its own (weekdays, day of month, month). */
function hasFrequencyFields(values: RecurrenceFormValues): boolean {
  if (values.frequency === 'weekly') {
    return values.weekdays.length > 0
  }
  if (values.frequency === 'monthly') {
    return hasMonthDay(values)
  }
  if (values.frequency === 'yearly') {
    return hasMonthDay(values) && values.year_month !== null
  }
  return true
}

/** Whether the picked end mode has its own value (a date, a count). */
function hasEndFields(values: RecurrenceFormValues): boolean {
  if (values.ends === 'on_date') {
    return values.ends_on !== null
  }
  if (values.ends === 'after_count') {
    return values.occurrence_count !== null && values.occurrence_count >= 1
  }
  return values.ends === 'never'
}

/**
 * The rule the editor is drafting, as `formatTaskRecurrenceRule` reads it,
 * for the live preview under the controls. `null` while the rule is off or
 * still missing a field it needs: a sentence with a hole ("Ogni mese il
 * giorno ") would read as a bug, so the editor shows a prompt instead.
 */
export function recurrencePreviewRule(values: RecurrenceFormValues): TaskRecurrenceRule | null {
  const { frequency, interval, ends } = values
  if (!values.enabled || frequency === null || ends === null || interval === null || interval < 1) {
    return null
  }
  if (!hasFrequencyFields(values) || !hasEndFields(values)) {
    return null
  }
  return {
    frequency,
    interval,
    weekdays: values.weekdays,
    month_mode: values.month_mode,
    month_day: values.month_day,
    ordinal: values.ordinal,
    ordinal_weekday: values.ordinal_weekday,
    year_month: values.year_month,
    workdays_only: values.workdays_only,
    ends,
    ends_on: values.ends_on,
    occurrence_count: values.occurrence_count,
  }
}
