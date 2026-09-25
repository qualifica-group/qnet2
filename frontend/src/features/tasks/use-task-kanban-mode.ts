import { useState } from 'react'

/** The Kanban's own sub-choice (spec 0157 D-2): grouped by task status, or by due date. */
export type TaskKanbanMode = 'status' | 'due'

const STORAGE_KEY = 'tasks.kanban.mode'
const DEFAULT_KANBAN_MODE: TaskKanbanMode = 'status'

function isTaskKanbanMode(value: string | null): value is TaskKanbanMode {
  return value === 'status' || value === 'due'
}

function readStoredKanbanMode(): TaskKanbanMode {
  if (typeof window === 'undefined') {
    return DEFAULT_KANBAN_MODE
  }
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)
    return isTaskKanbanMode(stored) ? stored : DEFAULT_KANBAN_MODE
  } catch {
    return DEFAULT_KANBAN_MODE
  }
}

/** Persists the "per stato"/"per scadenza" sub-choice per user (spec 0157 D-5), try/catch around storage. */
export function useTaskKanbanMode() {
  const [kanbanMode, setKanbanModeState] = useState<TaskKanbanMode>(readStoredKanbanMode)

  const setKanbanMode = (next: TaskKanbanMode) => {
    setKanbanModeState(next)
    try {
      window.localStorage.setItem(STORAGE_KEY, next)
    } catch {
      // Storage can be unavailable (private mode, quota): the toggle still works for this session.
    }
  }

  return { kanbanMode, setKanbanMode }
}
