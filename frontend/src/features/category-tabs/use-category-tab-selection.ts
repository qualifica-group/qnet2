import { useState } from 'react'
import type { CategoryTabLevel } from '@/features/category-tabs/types'

/** The `all` level keeps the entry it always had (spec 0064); the root level (spec 0337 D-2) has its own. */
function storageKey(sourceKey: string, level: CategoryTabLevel): string {
  return level === 'root' ? `${sourceKey}.category-tab.root` : `${sourceKey}.category-tab`
}

function readStoredCategoryId(sourceKey: string, level: CategoryTabLevel): number | null {
  try {
    const stored = window.localStorage.getItem(storageKey(sourceKey, level))
    if (stored === null) {
      return null
    }
    const parsed = Number(stored)
    return Number.isInteger(parsed) ? parsed : null
  } catch {
    return null
  }
}

type SelectionByLevel = Record<CategoryTabLevel, number | null>

/**
 * Persists the operator's selected category tab across reloads (spec 0064
 * AC-021), one entry per grid and per strip level (spec 0337: each level
 * remembers its own tab). "Tutte" (`null`) is the default. Round-trips
 * the raw id only: whether it still names a category the actor can see is the
 * caller's concern, since it depends on the categories query.
 */
export function useCategoryTabSelection(sourceKey: string, level: CategoryTabLevel = 'all') {
  const [selection, setSelection] = useState<SelectionByLevel>(() => ({
    all: readStoredCategoryId(sourceKey, 'all'),
    root: readStoredCategoryId(sourceKey, 'root'),
  }))

  const setCategoryId = (next: number | null) => {
    setSelection((current) => ({ ...current, [level]: next }))
    try {
      if (next === null) {
        window.localStorage.removeItem(storageKey(sourceKey, level))
      } else {
        window.localStorage.setItem(storageKey(sourceKey, level), String(next))
      }
    } catch {
      // Storage can be unavailable (private mode, quota): the tab still switches for this session.
    }
  }

  return { categoryId: selection[level], setCategoryId }
}
