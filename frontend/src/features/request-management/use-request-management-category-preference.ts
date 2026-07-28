import { useState } from 'react'

const STORAGE_KEY = 'request-management.category-tab'

/** The default when nothing was stored yet: the "Tutte" tab (no category scope). */
const DEFAULT_CATEGORY_ID: number | null = null

function readStoredCategoryId(): number | null {
  if (typeof window === 'undefined') {
    return DEFAULT_CATEGORY_ID
  }
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)
    if (stored === null) {
      return DEFAULT_CATEGORY_ID
    }
    const parsed = Number(stored)
    return Number.isInteger(parsed) ? parsed : DEFAULT_CATEGORY_ID
  } catch {
    return DEFAULT_CATEGORY_ID
  }
}

/**
 * Persists the operator's selected Product Category tab for the Gestione
 * Richieste table across reloads (spec 0064 AC-021). "Tutte" (no category) is
 * the default when nothing was stored yet. Mirrors
 * `features/projects/use-projects-view-preference.ts`'s hook shape.
 *
 * This hook only round-trips the raw preference: whether the stored id still
 * names a category the actor can see today is a concern of the caller (it
 * depends on the categories query), not of persistence itself.
 */
export function useRequestManagementCategoryPreference() {
  const [categoryId, setCategoryIdState] = useState<number | null>(readStoredCategoryId)

  const setCategoryId = (next: number | null) => {
    setCategoryIdState(next)
    try {
      if (next === null) {
        window.localStorage.removeItem(STORAGE_KEY)
      } else {
        window.localStorage.setItem(STORAGE_KEY, String(next))
      }
    } catch {
      // Storage can be unavailable (private mode, quota): the tab still switches for this session.
    }
  }

  return { categoryId, setCategoryId }
}
