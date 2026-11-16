/**
 * Pure KPI computations for the Task board's strip (global) and per-fase
 * header (D-5): everything here takes a set of ROOT tasks and `today`
 * (`Y-m-d`) and returns numbers, independent of any component.
 */

import type { BoardTask } from '@/features/work-orders/task-board/types'

/** "chiuso" = the status' `group` is closed_positive or closed_negative. */
export function isClosedTask(task: BoardTask): boolean {
  return task.task_status.group === 'closed_positive' || task.task_status.group === 'closed_negative'
}

function dueReference(task: BoardTask): string | null {
  return task.end_date ?? task.start_date
}

/** "scaduto" = non chiuso e (end_date ?? start_date) < oggi. */
export function isOverdue(task: BoardTask, today: string): boolean {
  const reference = dueReference(task)
  return !isClosedTask(task) && reference !== null && reference < today
}

export function isDueToday(task: BoardTask, today: string): boolean {
  return dueReference(task) === today
}

function sumEstimatedMinutes(tasks: BoardTask[]): number {
  return tasks.reduce((sum, task) => sum + (task.estimated_minutes ?? 0), 0)
}

function sumActualMinutes(tasks: BoardTask[]): number {
  return tasks.reduce((sum, task) => sum + task.actual_minutes, 0)
}

/** The global KPI strip above the board (D-5): counted over the commessa's own ROOT tasks. */
export interface TaskBoardMetrics {
  total: number
  overdueCount: number
  dueTodayCount: number
  estimatedMinutes: number
  actualMinutes: number
}

export function computeBoardMetrics(roots: BoardTask[], today: string): TaskBoardMetrics {
  return {
    total: roots.length,
    overdueCount: roots.filter((task) => isOverdue(task, today)).length,
    dueTodayCount: roots.filter((task) => isDueToday(task, today)).length,
    estimatedMinutes: sumEstimatedMinutes(roots),
    actualMinutes: sumActualMinutes(roots),
  }
}

/** The per-fase counters in each group header (D-5): counted over that fase's own ROOT tasks. */
export interface TaskBoardStageMetrics {
  count: number
  estimatedMinutes: number
  actualMinutes: number
}

export function computeStageMetrics(roots: BoardTask[]): TaskBoardStageMetrics {
  return {
    count: roots.length,
    estimatedMinutes: sumEstimatedMinutes(roots),
    actualMinutes: sumActualMinutes(roots),
  }
}
