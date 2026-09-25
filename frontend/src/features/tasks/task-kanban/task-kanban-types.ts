/**
 * Types for the /tasks Kanban (spec 0157 D-2..D-5): the shared row shape both
 * the "per stato" and "per scadenza" boards render, and the generic column
 * group they group rows into.
 */
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'
import type { TaskLookupRef, TaskNamedRef } from '@/features/tasks/types'
import type { TableRow } from '@/features/table/types'
import type { KanbanGroupParam } from '@/features/tasks/task-kanban/task-kanban-group-param'

/** `task_status`'s own grid projection, plus its phase (`TaskRowMapper::statusRef`) — the same `{id,name,color,icon}` shape `TaskLookupBadge` already renders. */
export interface TaskKanbanStatusRef extends TaskLookupRef {
  group: TaskStatusGroupValue
}

/**
 * One `POST /tables/tasks/rows` row, narrowed to the fields the Kanban reads.
 * Extends the generic `TableRow` (id/actions/editable) so it composes with
 * `fetchTableRows` unchanged — this is a VIEW of the same wire shape
 * `TaskColumnCatalog`/`TaskRowMapper` produce, not a parallel contract.
 */
export interface TaskKanbanRow extends TableRow {
  title: string
  task_status: TaskKanbanStatusRef | null
  task_priority: TaskLookupRef | null
  start_date: string | null
  end_date: string | null
  completion_percentage: number
  estimated_minutes: number | null
  actual_minutes: number
  is_blocked: boolean
  assignees: TaskNamedRef[]
  has_subtasks: boolean
}

/**
 * One column of either Kanban board (spec 0164 D-1): metadata only — the
 * rows themselves are no longer precomputed client-side, each column loads
 * its OWN block via `useTaskKanbanColumnRows(group.kanbanGroup, ...)`.
 */
export interface TaskKanbanGroup<Key extends string = string> {
  key: Key
  label: string
  /** A `BADGE_COLOR_TOKENS` token for the column's accent dot, or `null` for a neutral one. */
  color: string | null
  /** Whether a card may be dropped ONTO this column. */
  droppable: boolean
  /** Whether a card may be dragged OUT of this column. */
  draggable: boolean
  /** The `kanbanGroup` param this column's own row query sends to scope its rows server-side (spec 0164 D-2). */
  kanbanGroup: KanbanGroupParam
}

/** Narrows a `TableRow` to `TaskKanbanRow` — the fields above are always present on the `tasks` domain's own rows. */
export function asTaskKanbanRow(row: TableRow): TaskKanbanRow {
  return row as TaskKanbanRow
}
