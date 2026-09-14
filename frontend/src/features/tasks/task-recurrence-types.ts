/**
 * The `recurrence` slice of the Task data contract (spec 0120), split out of
 * `types.ts` to keep that file under the engineering size limits.
 */

/** `App\Enums\TaskRecurrenceFrequency` (spec 0120 D-1). */
export const TASK_RECURRENCE_FREQUENCIES = ['daily', 'weekly', 'monthly'] as const
export type TaskRecurrenceFrequency = (typeof TASK_RECURRENCE_FREQUENCIES)[number]

/** `App\Enums\TaskRecurrenceEnd` (spec 0120 D-1). */
export const TASK_RECURRENCE_END_MODES = ['on_date', 'after_count', 'never'] as const
export type TaskRecurrenceEndMode = (typeof TASK_RECURRENCE_END_MODES)[number]

/**
 * `GET /api/tasks/{task}` `data.recurrence` (spec 0120 data_contract): the
 * frozen rule plus its own `id`. Unlike the write payload below, the fields
 * NOT pertinent to the picked `frequency`/`ends` still come back — as `null`,
 * never omitted (e.g. `weekdays` is `null` on a `daily` series).
 */
export interface TaskRecurrenceDetail {
  id: number
  frequency: TaskRecurrenceFrequency
  interval: number
  weekdays: number[] | null
  month_day: number | null
  ends: TaskRecurrenceEndMode
  ends_on: string | null
  occurrence_count: number | null
}

/**
 * POST/PATCH `recurrence` (spec 0120 data_contract): the mirror image of
 * `TaskRecurrenceDetail` without `id` — but here the fields not pertinent to
 * the picked `frequency`/`ends` are OMITTED, not `null` (server-side
 * `prohibited_unless`), so the key's mere presence would 422.
 */
export interface TaskRecurrencePayload {
  frequency: TaskRecurrenceFrequency
  interval: number
  weekdays?: number[]
  month_day?: number
  ends: TaskRecurrenceEndMode
  ends_on?: string
  occurrence_count?: number
}
