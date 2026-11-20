import { useContext } from 'react'
import { AuthContext } from '@/features/auth/auth-context'

/** Namespace of every table's "advanced filters panel open" browser entry. */
const STORAGE_PREFIX = 'table-advanced-filters-open'

/** Stored value of an open panel; a closed panel has no entry at all. */
const OPEN_VALUE = '1'

/**
 * Per user and per domain: several people can share a browser, and the choice
 * follows the module (the same list embedded in many records keeps one setting).
 */
export function advancedFiltersOpenKey(userId: number, domain: string): string {
  return `${STORAGE_PREFIX}:${userId}:${domain}`
}

/** The storage key of the signed-in user's table, or null without a user (nothing is kept). */
export function useAdvancedFiltersOpenKey(domain: string): string | null {
  const userId = useContext(AuthContext)?.user?.id
  return userId === undefined ? null : advancedFiltersOpenKey(userId, domain)
}

/** Closed unless the user left the panel open on this table. */
export function readAdvancedFiltersOpen(key: string | null): boolean {
  if (key === null) {
    return false
  }
  try {
    return window.localStorage.getItem(key) === OPEN_VALUE
  } catch {
    return false
  }
}

/** Stores an open panel, or removes the entry when it is closed (closed is the default). */
export function writeAdvancedFiltersOpen(key: string | null, open: boolean): void {
  if (key === null) {
    return
  }
  try {
    if (open) {
      window.localStorage.setItem(key, OPEN_VALUE)
    } else {
      window.localStorage.removeItem(key)
    }
  } catch {
    // Storage can be unavailable (private mode, quota): the panel still toggles for this session.
  }
}
