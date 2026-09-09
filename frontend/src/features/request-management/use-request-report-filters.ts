import { useCallback, useState, type SetStateAction } from 'react'
import type {
  RequestReportCategory,
  RequestReportOperator,
  RequestReportRowMode,
  RequestReportSite,
} from '@/features/request-management/report-api'
import {
  ROW_MODES,
  requestReportDefaultValues,
  type RequestReportFormValues,
} from '@/features/request-management/request-report-schema'

const STORAGE_KEY = 'request-management.report-filters'

/** An absent key list, or a real one: the shape both narrowing fields are stored in. */
function isOptionalKeyList(value: unknown): boolean {
  return value === undefined || (Array.isArray(value) && value.every((key) => typeof key === 'string'))
}

/**
 * Guards the parsed payload: anything written by an older shape, or hand-edited,
 * is discarded rather than fed to the query as-is. Only the contract fields
 * are kept — they travel straight to `/report/dashboard` as query params.
 *
 * `operator_keys` (spec 0109) and `site_keys` (spec 0112) are checked
 * OPTIONALLY on purpose (0109 D-11): a payload written before either spec has
 * no such key, and rejecting it would throw away the dates and branches the
 * operator was working on because of a field that did not exist when they
 * picked them.
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
    ROW_MODES.includes(candidate.row_mode as RequestReportRowMode) &&
    isOptionalKeyList(candidate.operator_keys) &&
    isOptionalKeyList(candidate.site_keys)
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
          operator_keys: parsed.operator_keys ?? [],
          site_keys: parsed.site_keys ?? [],
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
 * Twin of {@see reconcileCategoryKeys} for the GA2 list (spec 0109, D-11):
 * drops operators the report no longer offers and, when nothing survives,
 * seeds the whole list — which is how "everything selected" becomes the
 * default the payload builder then omits from the wire (D-2).
 */
export function reconcileOperatorKeys(
  filters: RequestReportFormValues,
  operators: RequestReportOperator[],
): RequestReportFormValues {
  const allKeys = operators.map((operator) => operator.key)

  // No GA2 at all is a legitimate state (nothing assigned yet): there is
  // nothing to reconcile against, and seeding an empty list would only churn
  // the state. The branch twin leaves this case to its caller; here it is
  // handled inline because an empty operator list must NOT block the filters.
  if (allKeys.length === 0) {
    return filters
  }

  const kept = filters.operator_keys.filter((key) => allKeys.includes(key))

  if (kept.length === filters.operator_keys.length && kept.length > 0) {
    return filters
  }

  return { ...filters, operator_keys: kept.length > 0 ? kept : allKeys }
}

/**
 * Twin of {@see reconcileOperatorKeys} for the operational site list (spec
 * 0112, AC-019): drops sites the report no longer offers and, when nothing
 * survives, seeds the whole list — which is how "everything selected" becomes
 * the default the payload builder then omits from the wire (D-4).
 */
export function reconcileSiteKeys(
  filters: RequestReportFormValues,
  sites: RequestReportSite[],
): RequestReportFormValues {
  const allKeys = sites.map((site) => site.key)

  // No site at all is a legitimate state (no GA2 has a membership yet): there
  // is nothing to reconcile against, and seeding an empty list would only
  // churn the state — same guard, and same reason, as the operator twin.
  if (allKeys.length === 0) {
    return filters
  }

  const kept = filters.site_keys.filter((key) => allKeys.includes(key))

  if (kept.length === filters.site_keys.length && kept.length > 0) {
    return filters
  }

  return { ...filters, site_keys: kept.length > 0 ? kept : allKeys }
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

  /**
   * Accepts an updater, exactly like `useState` (spec 0109): the panel
   * reconciles the branch list and the operator list in two INDEPENDENT
   * effects, and both can land in the same commit — a plain value computed
   * from the render's `filters` would make the second write clobber the
   * first with a stale copy. Resolving against the live state is what keeps
   * the two reconciles composable, and it also means neither effect needs
   * `filters` in its dependency list.
   */
  const setFilters = useCallback((next: SetStateAction<RequestReportFormValues>) => {
    setFiltersState((current) => {
      const resolved = typeof next === 'function' ? next(current) : next

      // Same reference back = nothing changed (both reconcilers signal it that
      // way): React bails out of the re-render and there is nothing to persist.
      if (resolved === current) {
        return current
      }

      try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(resolved))
      } catch {
        // Storage can be unavailable (private mode, quota): the filters still
        // apply for this session.
      }

      return resolved
    })
  }, [])

  return { filters, setFilters }
}
