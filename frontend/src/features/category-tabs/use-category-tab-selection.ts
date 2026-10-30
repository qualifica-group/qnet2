import { useState } from 'react'

function storageKey(sourceKey: string): string {
  return `${sourceKey}.category-tab`
}

function readStoredCategoryId(sourceKey: string): number | null {
  try {
    const stored = window.localStorage.getItem(storageKey(sourceKey))
    if (stored === null) {
      return null
    }
    const parsed = Number(stored)
    return Number.isInteger(parsed) ? parsed : null
  } catch {
    return null
  }
}

/**
 * Persists the operator's selected category tab across reloads (spec 0064
 * AC-021), one entry per grid. "Tutte" (`null`) is the default. Round-trips
 * the raw id only: whether it still names a category the actor can see is the
 * caller's concern, since it depends on the categories query.
 */
export function useCategoryTabSelection(sourceKey: string) {
  const [categoryId, setCategoryIdState] = useState<number | null>(() => readStoredCategoryId(sourceKey))

  const setCategoryId = (next: number | null) => {
    setCategoryIdState(next)
    try {
      if (next === null) {
        window.localStorage.removeItem(storageKey(sourceKey))
      } else {
        window.localStorage.setItem(storageKey(sourceKey), String(next))
      }
    } catch {
      // Storage can be unavailable (private mode, quota): the tab still switches for this session.
    }
  }

  return { categoryId, setCategoryId }
}
