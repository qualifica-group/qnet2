/**
 * Orchestrator hook for `<WorkOrderTaskBoard>` (spec 0146 D-6): owns the
 * client-only state (filters, view mode, selection) and derives every value
 * the board's views/toolbar/KPI strip render from the single query payload,
 * through the pure functions already unit-tested in `task-board-filters.ts`/
 * `task-board-metrics.ts`. No component below this hook re-derives any of it.
 */

import { useMemo, useState } from 'react'
import { useAuth } from '@/features/auth/use-auth'
import {
  DEFAULT_TASK_BOARD_FILTERS,
  buildBoardTree,
  deriveTaskBoardFilterOptions,
  filterBoardTasks,
  groupRootsByStage,
} from '@/features/work-orders/task-board/task-board-filters'
import { computeBoardMetrics } from '@/features/work-orders/task-board/task-board-metrics'
import { useTaskBoard } from '@/features/work-orders/task-board/use-task-board'
import type { BoardTask, TaskBoardFilters } from '@/features/work-orders/task-board/types'

/** Today as `YYYY-MM-DD` in the ACTOR's own local calendar day (not UTC: a `Y-m-d` due date compares as a plain string). */
function todayIsoDate(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function toggleId(ids: Set<number>, id: number): Set<number> {
  const next = new Set(ids)
  if (next.has(id)) {
    next.delete(id)
  } else {
    next.add(id)
  }
  return next
}

export function useTaskBoardState(workOrderId: number) {
  const { user } = useAuth()
  const currentUserId = user?.id ?? 0
  const today = useMemo(() => todayIsoDate(), [])

  const boardQuery = useTaskBoard(workOrderId)
  const payload = boardQuery.data

  const [filters, setFilters] = useState<TaskBoardFilters>(DEFAULT_TASK_BOARD_FILTERS)
  const [selectedTaskIds, setSelectedTaskIds] = useState<Set<number>>(new Set())

  // Step 1: D-6 filters apply to roots, then the parent/children tree and the fase groups are rebuilt from what passed
  const filteredTasks = useMemo<BoardTask[]>(
    () => (payload ? filterBoardTasks(payload.tasks, filters, currentUserId, today) : []),
    [payload, filters, currentUserId, today],
  )
  const groups = useMemo(
    () => (payload ? groupRootsByStage(payload.stages, buildBoardTree(filteredTasks)) : []),
    [payload, filteredTasks],
  )
  /**
   * The SAME grouping, filters aside: `use-task-board-dnd.ts` computes a
   * drop's `position` against this one, never against the filtered `groups`
   * above — the backend's `position` is the index among ALL of a fase's
   * roots, and a client-side filter hiding some of them must never desync
   * their positions.
   */
  const allGroups = useMemo(
    () => (payload ? groupRootsByStage(payload.stages, buildBoardTree(payload.tasks)) : []),
    [payload],
  )

  // Step 2: the KPI strip and the filter option catalogs read the board's own UNFILTERED roots — they summarize the whole commessa, not the current view
  const roots = useMemo(() => payload?.tasks.filter((task) => task.parent_task_id === null) ?? [], [payload])
  const metrics = useMemo(() => computeBoardMetrics(roots, today), [roots, today])
  const filterOptions = useMemo(() => deriveTaskBoardFilterOptions(payload?.tasks ?? []), [payload])
  const tasksById = useMemo(() => new Map((payload?.tasks ?? []).map((task) => [task.id, task])), [payload])

  const hasAnyTask = (payload?.tasks.length ?? 0) > 0
  const hasVisibleTask = filteredTasks.length > 0
  const isReadOnly = payload?.is_read_only ?? true

  return {
    today,
    currentUserId,
    boardQuery,
    payload,
    isReadOnly,
    filters,
    setFilters,
    groups,
    allGroups,
    metrics,
    filterOptions,
    tasksById,
    hasAnyTask,
    hasVisibleTask,
    selectedTaskIds,
    toggleTaskSelection: (taskId: number) => setSelectedTaskIds((current) => toggleId(current, taskId)),
    clearSelection: () => setSelectedTaskIds(new Set()),
  }
}
