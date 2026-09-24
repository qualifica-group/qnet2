/**
 * The `recurrence` slice of the Task data contract (spec 0120, extended by
 * spec 0155 D-1), split out of `types.ts` to keep that file under the
 * engineering size limits.
 */

/** `App\Enums\TaskRecurrenceFrequency` (spec 0120 D-1, spec 0155 D-1 adds `yearly`/`custom`). */
export const TASK_RECURRENCE_FREQUENCIES = ['daily', 'weekly', 'monthly', 'yearly', 'custom'] as const
export type TaskRecurrenceFrequency = (typeof TASK_RECURRENCE_FREQUENCIES)[number]

/** `App\Enums\TaskRecurrenceEnd` (spec 0120 D-1). */
export const TASK_RECURRENCE_END_MODES = ['on_date', 'after_count', 'never'] as const
export type TaskRecurrenceEndMode = (typeof TASK_RECURRENCE_END_MODES)[number]

/**
 * Spec 0155 D-1: how a monthly/yearly rule picks its day within the month —
 * a fixed calendar day (`month_day`), or an ordinal weekday (`ordinal` +
 * `ordinal_weekday`, e.g. "the 2nd Tuesday"). Irrelevant to `daily`/`weekly`/
 * `custom`.
 */
export const TASK_RECURRENCE_MONTH_MODES = ['fixed', 'ordinal'] as const
export type TaskRecurrenceMonthMode = (typeof TASK_RECURRENCE_MONTH_MODES)[number]

/**
 * `GET /api/tasks/{task}` `data.recurrence` (spec 0120 data_contract, spec
 * 0155 D-1): the frozen rule plus its own `id`. Unlike the write payload
 * below, the fields NOT pertinent to the picked `frequency`/`month_mode`/
 * `ends` still come back — as `null`, never omitted (e.g. `weekdays` is
 * `null` on a `daily` series). `workdays_only` is the one exception: a plain
 * boolean, always present, never `null` (spec 0155 D-1: it is not
 * frequency-conditional).
 */
export interface TaskRecurrenceDetail {
  id: number
  frequency: TaskRecurrenceFrequency
  interval: number
  weekdays: number[] | null
  month_mode: TaskRecurrenceMonthMode | null
  month_day: number | null
  ordinal: number | null
  ordinal_weekday: number | null
  year_month: number | null
  workdays_only: boolean
  ends: TaskRecurrenceEndMode
  ends_on: string | null
  occurrence_count: number | null
}

/**
 * POST/PATCH `recurrence` (spec 0120 data_contract, spec 0155 D-1): the
 * mirror image of `TaskRecurrenceDetail` without `id` — but here the fields
 * not pertinent to the picked `frequency`/`month_mode`/`ends` are OMITTED,
 * not `null` (server-side `prohibited_unless`), so the key's mere presence
 * would 422. `workdays_only` is sent only when `true` (server default
 * `false` covers the unchecked case, same idiom as every other opt-in flag
 * in this contract).
 */
export interface TaskRecurrencePayload {
  frequency: TaskRecurrenceFrequency
  interval: number
  weekdays?: number[]
  month_mode?: TaskRecurrenceMonthMode
  month_day?: number
  ordinal?: number
  ordinal_weekday?: number
  year_month?: number
  workdays_only?: boolean
  ends: TaskRecurrenceEndMode
  ends_on?: string
  occurrence_count?: number
}
