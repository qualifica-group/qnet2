import { useCallback, useState } from 'react'
import type { RequestReportCategory, RequestReportRowMode } from '@/features/request-management/report-api'
import {
  ROW_MODES,
  requestReportDefaultValues,
  type RequestReportFormValues,
} from '@/features/request-management/request-report-schema'

const STORAGE_KEY = 'request-management.report-filters'

/**
 * Guards the parsed payload: anything written by an older shape, or hand-edited,
 * is discarded rather than fed to the query as-is. Only the four contract fields
 * are kept — they travel straight to `/report/dashboard` as query params.
 */
function isStoredFilters(value: unknown): value is RequestReportFormValues {
  if (typeof value !== 'object' || value === null) {
    return false
  }
  const candidate = value as Record<string, unknown>

  return (
    typeof candidate.date_from === 'string' &&
    typeof candidate.date_to === 'string' &&
    Array.isArray(candidate.category_keys) &&
    candidate.category_keys.every((key) => typeof key === 'string') &&
    ROW_MODES.includes(candidate.row_mode as RequestReportRowMode)
  )
}

function readStoredFilters(): RequestReportFormValues | null {
  if (typeof window === 'undefined') {
    return null
  }
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)
    if (stored === null) {
      return null
    }
    const parsed: unknown = JSON.parse(stored)

    return isStoredFilters(parsed)
      ? {
          date_from: parsed.date_from,
          date_to: parsed.date_to,
          category_keys: parsed.category_keys,
          row_mode: parsed.row_mode,
        }
      : null
  } catch {
    return null
  }
}

/**
 * Drops branch keys the report no longer offers and, when nothing survives,
 * falls back to the whole list — the same "everything selected" seeding the
 * filter sheet used to do (rev-2 AC-050). Returns the SAME object when there
 * is nothing to change, so the caller can skip a pointless write.
 */
export function reconcileCategoryKeys(
  filters: RequestReportFormValues,
  categories: RequestReportCategory[],
): RequestReportFormValues {
  const allKeys = categories.map((category) => category.key)
  const kept = filters.category_keys.filter((key) => allKeys.includes(key))

  if (kept.length === filters.category_keys.length && kept.length > 0) {
    return filters
  }

  return { ...filters, category_keys: kept.length > 0 ? kept : allKeys }
}

/**
 * Persists the filters applied to the Gestione Richieste dashboard across
 * reloads (user directive 2026-09-08), so reopening the panel comes back to
 * the selection the operator was working on instead of the current week with
 * every branch. Mirrors `useRequestManagementCategoryPreference`'s hook shape.
 *
 * This hook only round-trips the raw preference: whether the stored branch
 * keys still name branches the actor can see today is a concern of the caller
 * (it depends on the categories query) — see {@see reconcileCategoryKeys}.
 */
export function useRequestReportFilters() {
  const [filters, setFiltersState] = useState<RequestReportFormValues>(
    () => readStoredFilters() ?? requestReportDefaultValues(),
  )

  const setFilters = useCallback((next: RequestReportFormValues) => {
    setFiltersState(next)
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next))
    } catch {
      // Storage can be unavailable (private mode, quota): the filters still
      // apply for this session.
    }
  }, [])

  return { filters, setFilters }
}
