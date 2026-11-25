import { useState } from 'react'
import type { TaskBoardViewMode } from '@/features/work-orders/task-board/types'
import type { WorkOrderType } from '@/features/work-orders/types'

const VIEW_MODES: readonly TaskBoardViewMode[] = ['roadmap', 'list', 'kanban']

/**
 * One remembered choice per work order type, so switching view in a project
 * work order leaves the processing ones alone. The processing key is the one
 * used before the Roadmap existed: choices already stored there are kept.
 */
const STORAGE_KEYS: Record<WorkOrderType, string> = {
  processing: 'work-orders.task-board.view-mode',
  project: 'project-work-orders.task-board.view-mode',
}

/** Project work orders open on the Roadmap (user request 2026-11-25); processing ones keep Lista. */
const DEFAULT_VIEW_MODES: Record<WorkOrderType, TaskBoardViewMode> = {
  processing: 'list',
  project: 'roadmap',
}

function isTaskBoardViewMode(value: string | null): value is TaskBoardViewMode {
  return VIEW_MODES.includes(value as TaskBoardViewMode)
}

function readStoredViewMode(workOrderType: WorkOrderType): TaskBoardViewMode {
  const fallback = DEFAULT_VIEW_MODES[workOrderType]
  if (typeof window === 'undefined') {
    return fallback
  }
  try {
    const stored = window.localStorage.getItem(STORAGE_KEYS[workOrderType])
    return isTaskBoardViewMode(stored) ? stored : fallback
  } catch {
    return fallback
  }
}

/** Persists the Roadmap/Lista/Board toggle across reloads (D-5), per work order type, try/catch around storage. */
export function useTaskBoardViewMode(workOrderType: WorkOrderType) {
  const [viewMode, setViewModeState] = useState<TaskBoardViewMode>(() => readStoredViewMode(workOrderType))

  const setViewMode = (next: TaskBoardViewMode) => {
    setViewModeState(next)
    try {
      window.localStorage.setItem(STORAGE_KEYS[workOrderType], next)
    } catch {
      // Storage can be unavailable (private mode, quota): the toggle still works for this session.
    }
  }

  return { viewMode, setViewMode }
}
