import { z } from 'zod'
import type { CustomFilterState } from '@/features/table/custom-filters/use-custom-filter-state'

/** Namespace of every table's browser-persisted filter entry. */
const STORAGE_PREFIX = 'table-filters'

/**
 * The filters a table keeps in the browser across a reload: the quick-search
 * text and the active custom filter (spec 0158). Column and advanced filters
 * are NOT here: they are persisted server-side (`user_table_filters`).
 */
export interface TableLocalFilters {
  search: string
  customFilter: CustomFilterState | null
}

/** Identifies one table instance: the user, the domain and its row-set scope. */
export interface TableLocalFiltersScope {
  userId: number
  domain: string
  productCategoryId?: number
  opportunityId?: number
  quoteId?: number
}

export const EMPTY_TABLE_LOCAL_FILTERS: TableLocalFilters = { search: '', customFilter: null }

const ruleSchema = z.object({
  field: z.string(),
  operator: z.string(),
  value: z.unknown().optional(),
})

// Malformed parts fall back to "no filter" instead of failing the whole entry:
// the stored value is written by an older build or edited by hand, never trusted.
const storedFiltersSchema = z.object({
  search: z.string().catch(''),
  customFilter: z
    .object({
      rules: z.object({ and: z.array(ruleSchema), or: z.array(ruleSchema) }),
      viewId: z.number().int().optional(),
      name: z.string().optional(),
    })
    .nullable()
    .catch(null),
})

/**
 * Per-user key: several people can share a browser, and one user's filters
 * must never open on another's table. The scope parts keep an embedded,
 * scoped table (e.g. an Opportunity's Quotes panel) apart from the module page.
 */
export function tableLocalFiltersKey(scope: TableLocalFiltersScope): string {
  return [
    STORAGE_PREFIX,
    scope.userId,
    scope.domain,
    scope.productCategoryId ?? '',
    scope.opportunityId ?? '',
    scope.quoteId ?? '',
  ].join(':')
}

export function readTableLocalFilters(key: string | null): TableLocalFilters {
  if (key === null) {
    return EMPTY_TABLE_LOCAL_FILTERS
  }
  try {
    const stored = window.localStorage.getItem(key)
    if (stored === null) {
      return EMPTY_TABLE_LOCAL_FILTERS
    }
    const parsed = storedFiltersSchema.safeParse(JSON.parse(stored))
    return parsed.success ? parsed.data : EMPTY_TABLE_LOCAL_FILTERS
  } catch {
    return EMPTY_TABLE_LOCAL_FILTERS
  }
}

/** Stores the filters, or removes the entry when none is active (no empty leftovers). */
export function writeTableLocalFilters(key: string | null, filters: TableLocalFilters): void {
  if (key === null) {
    return
  }
  try {
    if (filters.search.trim() === '' && filters.customFilter === null) {
      window.localStorage.removeItem(key)
    } else {
      window.localStorage.setItem(key, JSON.stringify(filters))
    }
  } catch {
    // Storage can be unavailable (private mode, quota): the filters still apply for this session.
  }
}
