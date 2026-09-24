import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import type { AdvancedFilterValues } from '@/features/table/advanced-filters/types'

const STATUS_PARAM = 'status'
const ASSIGNMENT_PARAM = 'assignment'
const DEFAULT_STATUS = 'open'

/** Mirrors `App\Enums\TaskListStatus` values (spec 0151 D-5). */
const KNOWN_STATUS_VALUES = new Set(['open', 'completed', 'blocked', 'all', 'in_validation'])

/**
 * Mirrors `App\Enums\TaskAssignmentScope` values (spec 0151 D-3) plus the
 * `all` ("task in cui ho un ruolo") and `visible` ("tutti i visibili")
 * values added by spec 0153 D-1.
 */
const KNOWN_ASSIGNMENT_VALUES = new Set([
  'assigned_to_me',
  'requested_by_me',
  'assigned_by_me',
  'created_by_me',
  'observed_by_me',
  'all',
  'visible',
])

export interface TaskListUrlFilters {
  /**
   * `{ status, assignment }` for this visit only, or `null` when the URL
   * carries no recognized `status`/`assignment` value (spec 0151 D-2). Feeds
   * `TableView`'s `advancedFiltersOverride`.
   */
  override: AdvancedFilterValues | null
  /**
   * Strips `status`/`assignment` from the URL, e.g. once the advanced-filters
   * panel's Apply/Reset takes back over (spec 0151 D-2). Feeds `TableView`'s
   * `onAdvancedFiltersOverrideCleared`.
   */
  clear: () => void
}

/**
 * Reads the Tasks dashboard cards' one-time `status`/`assignment` deep link
 * off the URL (spec 0151 `frontend_url` /tasks, D-2/D-5): unknown values are
 * ignored, and `assignment` alone defaults `status` to `open`. No recognized
 * value ⇒ `override` is `null`, i.e. today's behavior (persisted filters
 * apply as usual).
 */
export function useTaskListUrlFilters(): TaskListUrlFilters {
  const [searchParams, setSearchParams] = useSearchParams()

  const statusParam = searchParams.get(STATUS_PARAM)
  const status = statusParam !== null && KNOWN_STATUS_VALUES.has(statusParam) ? statusParam : null

  // `assignment` is now a multi-value filter (spec 0153 D-1): the URL may
  // repeat the param (`assignment=a&assignment=b`), each recognized value
  // kept in the order given, unknown ones dropped.
  const assignmentValues = searchParams
    .getAll(ASSIGNMENT_PARAM)
    .filter((value) => KNOWN_ASSIGNMENT_VALUES.has(value))
  const assignment = assignmentValues.length > 0 ? assignmentValues : null

  const override = useMemo<AdvancedFilterValues | null>(() => {
    if (status === null && assignment === null) {
      return null
    }
    const values: AdvancedFilterValues = { [STATUS_PARAM]: status ?? DEFAULT_STATUS }
    if (assignment !== null) {
      values[ASSIGNMENT_PARAM] = assignment
    }
    return values
    // eslint-disable-next-line react-hooks/exhaustive-deps -- `assignment` is a fresh array each render; its own content (via `searchParams`) is what should trigger recompute, not identity.
  }, [status, searchParams])

  const clear = useCallback(() => {
    const next = new URLSearchParams(searchParams)
    next.delete(STATUS_PARAM)
    next.delete(ASSIGNMENT_PARAM)
    setSearchParams(next, { replace: true })
  }, [searchParams, setSearchParams])

  return { override, clear }
}
