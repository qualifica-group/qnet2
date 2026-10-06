import type { ChangeEvent } from 'react'
import type { TaskRecurrenceFrequency } from '@/features/tasks/types'

/** The one protected field (spec 0120 D-12) every control of the recurrence editor shares. */
export const RECURRENCE_META_KEY = 'recurrence'

/** Every rule field the editor clears on a change of branch, with the same dirty flag. */
export const RECURRENCE_RESET_OPTIONS = { shouldDirty: true } as const

/** Bare `<Input type="number">`, min-1, tolerating a blank box while typing (mirrors `TaskPlanningSection`). */
export function numberInputProps(value: number | null, onChange: (next: number | null) => void) {
  return {
    value: value ?? '',
    onChange: (event: ChangeEvent<HTMLInputElement>) =>
      onChange(event.target.value === '' ? null : Number(event.target.value)),
  }
}

/**
 * The unit "Ripeti ogni N ..." counts in. `custom` is q-net's "every N days"
 * (spec 0155 D-1, same as `formatTaskRecurrenceRule`), so it counts days too.
 */
const INTERVAL_UNITS = {
  daily: 'day',
  custom: 'day',
  weekly: 'week',
  monthly: 'month',
  yearly: 'year',
} as const satisfies Record<TaskRecurrenceFrequency, string>

export function intervalUnitKey(frequency: TaskRecurrenceFrequency | null) {
  return `tasks.form.recurrence.intervalUnit.${INTERVAL_UNITS[frequency ?? 'daily']}` as const
}
