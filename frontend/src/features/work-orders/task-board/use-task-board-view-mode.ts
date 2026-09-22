import { useState } from 'react'
import type { TaskBoardViewMode } from '@/features/work-orders/task-board/types'

const STORAGE_KEY = 'work-orders.task-board.view-mode'
const DEFAULT_VIEW_MODE: TaskBoardViewMode = 'list'

function isTaskBoardViewMode(value: string | null): value is TaskBoardViewMode {
  return value === 'list' || value === 'kanban'
}

function readStoredViewMode(): TaskBoardViewMode {
  if (typeof window === 'undefined') {
    return DEFAULT_VIEW_MODE
  }
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)
    return isTaskBoardViewMode(stored) ? stored : DEFAULT_VIEW_MODE
  } catch {
    return DEFAULT_VIEW_MODE
  }
}

/** Persists the Lista/Board toggle across reloads (D-5), try/catch around storage. Lista is the default when nothing was stored yet. */
export function useTaskBoardViewMode() {
  const [viewMode, setViewModeState] = useState<TaskBoardViewMode>(readStoredViewMode)

  const setViewMode = (next: TaskBoardViewMode) => {
    setViewModeState(next)
    try {
      window.localStorage.setItem(STORAGE_KEY, next)
    } catch {
      // Storage can be unavailable (private mode, quota): the toggle still works for this session.
    }
  }

  return { viewMode, setViewMode }
}
