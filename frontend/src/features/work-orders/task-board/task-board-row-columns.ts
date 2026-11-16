/**
 * The list view's column layout (spec 0401 D-2/D-7): ONE grid template shared
 * by each phase's header row and every task row, so the values line up under
 * their labels like the Task grid. Below `lg` the row stacks instead (two
 * cells per line, each with its own label).
 */

import { createContext } from 'react'
import type { BoardTask } from '@/features/work-orders/task-board/types'

/** Titolo, Stato, Tipo, Priorita', Data inizio, Data fine, Richiedente, Assegnatari, Completamento, Ore. */
export const TASK_BOARD_CELLS_CLASS =
  'grid min-w-0 flex-1 grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-3 lg:grid-cols-[minmax(14rem,1fr)_8rem_7rem_7rem_6rem_6.5rem_8rem_8rem_7rem_9rem] lg:items-center lg:gap-x-3 lg:gap-y-0'

/** The title cell spans the whole first line while the row is stacked. */
export const TASK_BOARD_TITLE_CELL_CLASS = 'col-span-2 sm:col-span-3 lg:col-span-1'

/** One task line: a single row of cells from `lg` up, stacked below. */
export const TASK_BOARD_ROW_CLASS =
  'flex cursor-pointer items-start gap-2 px-3 py-2 transition-colors hover:bg-muted/30 lg:items-center lg:py-1'

/**
 * Drag handle + selection checkbox in front of the cells: fixed width, so a
 * sub-task row (which has neither) keeps its cells under the same headers.
 */
export const TASK_BOARD_LEADING_CLASS = 'flex w-10 shrink-0 items-center gap-1.5 pt-1 lg:pt-0'

/** The rows' own minimum width from `lg` up: past it the phase card scrolls sideways (D-7), never the page. */
export const TASK_BOARD_ROWS_MIN_WIDTH_CLASS = 'lg:min-w-[91.75rem]'

/** The header labels, in column order (`workOrders.taskBoard.task.columns.*`). */
export const TASK_BOARD_COLUMN_KEYS = [
  'title',
  'status',
  'type',
  'priority',
  'start',
  'due',
  'requester',
  'assignees',
  'completion',
  'hours',
] as const

/** The Task grid's own column ids the cell PATCH accepts (spec 0401 D-4). */
export type TaskBoardEditableColumn =
  | 'title'
  | 'task_status'
  | 'task_type'
  | 'task_priority'
  | 'start_date'
  | 'end_date'
  | 'requester'
  | 'assignees'
  | 'estimated_minutes'

/** Each editable column's key in the task's `permissions.fields`. */
const PERMISSION_FIELD: Record<TaskBoardEditableColumn, string> = {
  title: 'title',
  task_status: 'task_status_id',
  task_type: 'task_type_id',
  task_priority: 'task_priority_id',
  start_date: 'start_date',
  end_date: 'end_date',
  requester: 'requester_id',
  assignees: 'assignee_ids',
  estimated_minutes: 'estimated_minutes',
}

/** Whether the actor may edit this cell: the task's own `update` plus the field's `editable` (absent = editable, as `useResourcePermissions`). */
export function canEditTaskCell(task: BoardTask, column: TaskBoardEditableColumn): boolean {
  const { resource, fields } = task.permissions
  return resource.update && (fields[PERMISSION_FIELD[column]]?.editable ?? true)
}

export type TaskBoardCellValue = string | number | number[] | null

/** What a row needs to write: provided by the list view, absent (read-only rows) in the drag overlay. */
export interface TaskBoardCellEdit {
  commit: (task: BoardTask, column: TaskBoardEditableColumn, value: TaskBoardCellValue) => void
  complete: (task: BoardTask) => void
  reopen: (task: BoardTask) => void
}

export const TaskBoardCellEditContext = createContext<TaskBoardCellEdit | null>(null)
