/**
 * Wire shape of `kanbanGroup` (spec 0164 D-2), the SAME `POST
 * /tables/tasks/rows` request param every Kanban column attaches to its own
 * row query, scoping the response to that column alone.
 *
 * Deliberately dependency-free (no import from `task-kanban-types.ts` or
 * `task-kanban-due-buckets.ts`): both of those already depend on each other
 * transitively, so anchoring `DUE_BUCKET_KEYS`/`DueBucketKey` here — instead
 * of inside `task-kanban-due-buckets.ts`, which re-exports them unchanged —
 * is what keeps this module import-cycle-free for either of them.
 */

/** The seven fixed "per scadenza" bucket keys, in display order (mirrors the backend's own allow-list). */
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

/** `by`/`key` pair a column sends to scope its own rows server-side. */
export type KanbanGroupParam = { by: 'status'; key: number } | { by: 'due'; key: DueBucketKey }
