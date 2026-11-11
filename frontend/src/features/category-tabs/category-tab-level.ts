import { useState } from 'react'
import type { CategoryTabLevel } from '@/features/category-tabs/types'

function storageKey(sourceKey: string): string {
  return `${sourceKey}.category-tab-level`
}

/** Root categories unless the operator switched the strip to every category (decisione utente 2026-11-05). */
const DEFAULT_LEVEL: CategoryTabLevel = 'root'

function readStoredLevel(sourceKey: string, defaultLevel: CategoryTabLevel): CategoryTabLevel {
  try {
    const stored = window.localStorage.getItem(storageKey(sourceKey))
    return stored === 'all' || stored === 'root' ? stored : defaultLevel
  } catch {
    return defaultLevel
  }
}

/**
 * The extra argument the strip's fetchers take: only the root level names
 * itself, so the `all` level calls (and their cache) stay exactly as before
 * spec 0337.
 */
export function levelArgs(level: CategoryTabLevel): [] | ['root'] {
  return level === 'root' ? ['root'] : []
}

/** The `level` query of the strip's endpoints (spec 0337): sent only for the root level, so `all` is the request of today. */
export function levelRequestConfig(level: CategoryTabLevel): { params: { level: 'root' } } | undefined {
  return level === 'root' ? { params: { level } } : undefined
}

/**
 * The operator's chosen strip level (spec 0337 D-2), kept in the browser per
 * grid: only the root categories (default) or every category. The statistics
 * strip starts from every category instead (spec 0385).
 */
export function useCategoryTabLevel(sourceKey: string, defaultLevel: CategoryTabLevel = DEFAULT_LEVEL) {
  const [level, setLevelState] = useState<CategoryTabLevel>(() => readStoredLevel(sourceKey, defaultLevel))

  const setLevel = (next: CategoryTabLevel) => {
    setLevelState(next)
    try {
      window.localStorage.setItem(storageKey(sourceKey), next)
    } catch {
      // Storage can be unavailable (private mode, quota): the level still switches for this session.
    }
  }

  return { level, setLevel }
}
