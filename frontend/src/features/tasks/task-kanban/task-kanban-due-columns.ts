/**
 * Column metadata for the "per scadenza" Kanban (spec 0164 D-1/D-2, spec 0157
 * D-4 revised to add "Più avanti"): the seven fixed buckets of
 * `task-kanban-due-buckets.ts`, in a fixed display order. Each column loads
 * its OWN rows server-side via `kanbanGroup` — the FE no longer classifies a
 * row into a bucket for grouping purposes (`classifyTaskDueBucket` stays in
 * use elsewhere, for the card's own due chip styling).
 */
import type { TFunction } from 'i18next'
import {
  DUE_BUCKET_KEYS,
  isDueBucketDraggableFrom,
  isDueBucketDroppable,
  type DueBucketKey,
} from '@/features/tasks/task-kanban/task-kanban-due-buckets'
import type { TaskKanbanGroup } from '@/features/tasks/task-kanban/task-kanban-types'

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

export function buildTaskDueKanbanGroups(t: TFunction): TaskKanbanGroup<DueBucketKey>[] {
  return DUE_BUCKET_KEYS.map((key) => ({
    key,
    label: t(`tasks.views.dueBuckets.${key}`),
    color: DUE_BUCKET_COLOR[key],
    droppable: isDueBucketDroppable(key),
    draggable: isDueBucketDraggableFrom(key),
    kanbanGroup: { by: 'due', key },
  }))
}
