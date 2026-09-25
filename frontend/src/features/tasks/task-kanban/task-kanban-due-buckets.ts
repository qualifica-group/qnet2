/**
 * Pure date logic for the "per scadenza" Kanban (spec 0157 D-2/D-4, revised
 * by user directive: adds "Più avanti"): classifies a row into one of seven
 * fixed buckets and computes the `end_date` a drop onto a given bucket
 * writes. Independent of any component, so the classification/drop rules
 * are unit-testable without mounting dnd-kit.
 */
import type { TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'

/** The seven fixed columns of the "per scadenza" board, in display order. */
export const DUE_BUCKET_KEYS = [
  'overdue',
  'today',
  'tomorrow',
  'this_week',
  'this_month',
  'later',
  'completed',
] as const

export type DueBucketKey = (typeof DUE_BUCKET_KEYS)[number]

function isClosedTask(row: TaskKanbanRow): boolean {
  const group = row.task_status?.group
  return group === 'closed_positive' || group === 'closed_negative'
}

/** `end_date ?? start_date` (mirrors the backend's own due reference, `DUE_REFERENCE_SQL`). */
function dueReference(row: TaskKanbanRow): string | null {
  return row.end_date ?? row.start_date
}

/**
 * Parses a `Y-m-d` string as a UTC midnight instant and formats it back the
 * same way, so every date-arithmetic step below stays in UTC on purpose —
 * a plain `new Date(str)` + local getters would shift by a day depending on
 * the runner's own timezone.
 */
function parseUtcDate(date: string): Date {
  const [year, month, day] = date.split('-').map(Number)
  return new Date(Date.UTC(year, month - 1, day))
}

function formatUtcDate(date: Date): string {
  return date.toISOString().slice(0, 10)
}

function addUtcDays(date: string, days: number): string {
  const parsed = parseUtcDate(date)
  parsed.setUTCDate(parsed.getUTCDate() + days)
  return formatUtcDate(parsed)
}

/** Sunday of the ISO week `date` falls in, as `Y-m-d` (mirrors the Task board's own week bounds). */
function endOfIsoWeek(date: string): string {
  const parsed = parseUtcDate(date)
  const isoWeekday = parsed.getUTCDay() === 0 ? 7 : parsed.getUTCDay()
  parsed.setUTCDate(parsed.getUTCDate() + (7 - isoWeekday))
  return formatUtcDate(parsed)
}

/** Last day of the month `date` falls in, as `Y-m-d`. */
function endOfMonth(date: string): string {
  const parsed = parseUtcDate(date)
  const lastDay = new Date(Date.UTC(parsed.getUTCFullYear(), parsed.getUTCMonth() + 1, 0))
  return formatUtcDate(lastDay)
}

/** The 1st of the month AFTER the one `date` falls in, as `Y-m-d`. */
function startOfNextMonth(date: string): string {
  const parsed = parseUtcDate(date)
  const firstOfNext = new Date(Date.UTC(parsed.getUTCFullYear(), parsed.getUTCMonth() + 1, 1))
  return formatUtcDate(firstOfNext)
}

/**
 * Classifies a row into its column (spec D-2, revised by user directive):
 * closed tasks always land in "Completati" regardless of date. Among open
 * tasks, the reference date is matched against overdue/today/tomorrow/
 * this-week/this-month in that order; "Questo mese" is now the CURRENT
 * calendar month only, plus a task with NO due reference at all ("anche
 * senza scadenza") — a reference past the end of this month is "Più avanti"
 * instead, so every open task still always has a column (D-4: the board
 * never drops a task it loaded).
 */
export function classifyTaskDueBucket(row: TaskKanbanRow, today: string): DueBucketKey {
  if (isClosedTask(row)) {
    return 'completed'
  }

  const reference = dueReference(row)
  if (reference === null) {
    return 'this_month'
  }
  if (reference < today) {
    return 'overdue'
  }
  if (reference === today) {
    return 'today'
  }
  if (reference === addUtcDays(today, 1)) {
    return 'tomorrow'
  }
  if (reference <= endOfIsoWeek(today)) {
    return 'this_week'
  }
  if (reference <= endOfMonth(today)) {
    return 'this_month'
  }
  return 'later'
}

/** Whether a card may be dropped ONTO `key` (D-2: "Scaduti" and "Completati" refuse an incoming drop). */
export function isDueBucketDroppable(key: DueBucketKey): boolean {
  return key !== 'overdue' && key !== 'completed'
}

/** Whether a card may be dragged OUT of `key` (D-2: "Completati" is fully locked). */
export function isDueBucketDraggableFrom(key: DueBucketKey): boolean {
  return key !== 'completed'
}

/**
 * The `end_date` a drop onto `key` writes (D-2), or `null` for a bucket that
 * is not a valid drop target — the caller must have already checked
 * {@link isDueBucketDroppable}. "Più avanti" writes the 1st of NEXT month
 * (user directive): local calendar date, computed the same UTC-anchored way
 * as every other bucket so it never shifts by a day across timezones.
 */
export function dueBucketDropDate(key: DueBucketKey, today: string): string | null {
  switch (key) {
    case 'today':
      return today
    case 'tomorrow':
      return addUtcDays(today, 1)
    case 'this_week':
      return endOfIsoWeek(today)
    case 'this_month':
      return endOfMonth(today)
    case 'later':
      return startOfNextMonth(today)
    default:
      return null
  }
}
