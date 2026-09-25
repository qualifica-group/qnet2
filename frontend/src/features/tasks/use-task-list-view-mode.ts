import { useState } from 'react'

/** The three /tasks list modes (spec 0157 D-1/D-2): flat grid, tree grid, or kanban. */
export type TaskListViewMode = 'analytic' | 'synthetic' | 'kanban'

const STORAGE_KEY = 'tasks.list.view-mode'
const DEFAULT_VIEW_MODE: TaskListViewMode = 'analytic'

function isTaskListViewMode(value: string | null): value is TaskListViewMode {
  return value === 'analytic' || value === 'synthetic' || value === 'kanban'
}

function readStoredViewMode(): TaskListViewMode {
  if (typeof window === 'undefined') {
    return DEFAULT_VIEW_MODE
  }
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)
    return isTaskListViewMode(stored) ? stored : DEFAULT_VIEW_MODE
  } catch {
    return DEFAULT_VIEW_MODE
  }
}

/**
 * Persists the Analitica/Sintetica/Kanban choice per user (spec 0157 D-5),
 * try/catch around storage — mirrors `use-task-board-view-mode.ts`. Analitica
 * (today's flat grid) is the default when nothing was stored yet.
 */
export function useTaskListViewMode() {
  const [viewMode, setViewModeState] = useState<TaskListViewMode>(readStoredViewMode)

  const setViewMode = (next: TaskListViewMode) => {
    setViewModeState(next)
    try {
      window.localStorage.setItem(STORAGE_KEY, next)
    } catch {
      // Storage can be unavailable (private mode, quota): the toggle still works for this session.
    }
  }

  return { viewMode, setViewMode }
}
