import { useState } from 'react'
import type { CategoryTabLevel } from '@/features/category-tabs/types'

function storageKey(sourceKey: string): string {
  return `${sourceKey}.category-tab-level`
}

function readStoredLevel(sourceKey: string): CategoryTabLevel {
  try {
    return window.localStorage.getItem(storageKey(sourceKey)) === 'root' ? 'root' : 'all'
  } catch {
    return 'all'
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
 * grid: every category (default) or only the root ones.
 */
export function useCategoryTabLevel(sourceKey: string) {
  const [level, setLevelState] = useState<CategoryTabLevel>(() => readStoredLevel(sourceKey))

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
