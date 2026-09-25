/**
 * Pure grouping for the "per scadenza" Kanban (spec 0157 D-2/D-4, revised by
 * user directive to add "Più avanti"): the seven fixed buckets of
 * `task-kanban-due-buckets.ts`, filled with the rows that classify into
 * each, in a fixed display order.
 */
import type { TFunction } from 'i18next'
import {
  classifyTaskDueBucket,
  DUE_BUCKET_KEYS,
  isDueBucketDraggableFrom,
  isDueBucketDroppable,
  type DueBucketKey,
} from '@/features/tasks/task-kanban/task-kanban-due-buckets'
import type { TaskKanbanGroup, TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'

/** A `BADGE_COLOR_TOKENS` token per bucket, purely decorative (the column dot). */
const DUE_BUCKET_COLOR: Record<DueBucketKey, string | null> = {
  overdue: 'red',
  today: 'amber',
  tomorrow: 'blue',
  this_week: 'blue',
  this_month: 'slate',
  later: 'violet',
  completed: 'green',
}

export function buildTaskDueKanbanGroups(
  rows: TaskKanbanRow[],
  today: string,
  t: TFunction,
): TaskKanbanGroup<DueBucketKey>[] {
  const rowsByBucket = new Map<DueBucketKey, TaskKanbanRow[]>()
  for (const row of rows) {
    const bucket = classifyTaskDueBucket(row, today)
    const list = rowsByBucket.get(bucket) ?? []
    list.push(row)
    rowsByBucket.set(bucket, list)
  }

  return DUE_BUCKET_KEYS.map((key) => ({
    key,
    label: t(`tasks.views.dueBuckets.${key}`),
    color: DUE_BUCKET_COLOR[key],
    rows: rowsByBucket.get(key) ?? [],
    droppable: isDueBucketDroppable(key),
    draggable: isDueBucketDraggableFrom(key),
  }))
}
