import { useState } from 'react'
import { ROADMAP_SCALES, type RoadmapScale } from '@/features/work-orders/task-board/task-board-roadmap'

const STORAGE_KEY = 'work-orders.task-board.roadmap-scale'
const DEFAULT_SCALE: RoadmapScale = 'week'

function isRoadmapScale(value: string | null): value is RoadmapScale {
  return ROADMAP_SCALES.includes(value as RoadmapScale)
}

function readStoredScale(): RoadmapScale {
  if (typeof window === 'undefined') {
    return DEFAULT_SCALE
  }
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)
    return isRoadmapScale(stored) ? stored : DEFAULT_SCALE
  } catch {
    return DEFAULT_SCALE
  }
}

/** Persists the Roadmap's Settimane/Mesi/Anno choice across reloads, try/catch around storage (mirrors `use-task-board-view-mode.ts`). */
export function useRoadmapScale() {
  const [scale, setScaleState] = useState<RoadmapScale>(readStoredScale)

  const setScale = (next: RoadmapScale) => {
    setScaleState(next)
    try {
      window.localStorage.setItem(STORAGE_KEY, next)
    } catch {
      // Storage can be unavailable (private mode, quota): the toggle still works for this session.
    }
  }

  return { scale, setScale }
}
