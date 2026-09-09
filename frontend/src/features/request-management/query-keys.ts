/**
 * Centralized query keys for the request-management feature (mirrors the
 * opportunities' `opportunityDetailQueryKey` fresh-on-open pattern).
 */

export const requestManagementKeys = {
  /**
   * Query key of a single work panel (fresh-on-open pattern). Shared by the
   * panel's fetch and by the post-mutation invalidation, so they can never
   * drift apart. `null` (an unparsable route param) is a key that is never
   * fetched.
   */
  panel: (id: number | null) => ['request-management', 'panel', id] as const,
  /** Query key of the Product Category tab strip (spec 0064). */
  categories: () => ['request-management', 'categories'] as const,
  /**
   * Query key of the create form's "Informazioni aggiuntive" resolution (user
   * directive 2026-08-07): the criteria themselves, so switching category and
   * back re-reads the cache instead of refetching.
   */
  formContext: (criteriaKey: string) => ['request-management', 'form-context', criteriaKey] as const,
  /**
   * Query key of a category's "does it expose exactly one product?" probe
   * (user directive 2026-09-09). Its OWN key and not `forSelectKeys.list`:
   * that one belongs to the pickers' infinite query, whose cached shape is a
   * page list, not the single product resolved here.
   */
  categorySoleProduct: (categoryId: number) =>
    ['request-management', 'category-sole-product', categoryId] as const,
  /** Query key of the active category tab's resolved G.A. labels (spec 0080), the create form's own fetch. */
  categoryManagerLabels: (categoryId: number | null) =>
    ['request-management', 'category-manager-labels', categoryId] as const,
  /**
   * Query key of a CSV report run's poll (spec 0106). `null` before a run
   * exists yet — a stable, inert key `useRequestReport` disables the query on.
   */
  reportRun: (reportRunId: number | null) => ['request-management', 'report', reportRunId] as const,
  /** Query key of the report's selectable branches (spec 0106 rev-2). */
  reportCategories: () => ['request-management', 'report-categories'] as const,
  /** Query key of the report's selectable GA2 operators (spec 0109). */
  reportOperators: () => ['request-management', 'report-operators'] as const,
  /** Query key of the report's selectable operational sites (spec 0112). */
  reportSites: () => ['request-management', 'report-sites'] as const,
  /**
   * Query key of the dashboard's aggregates (spec 0107), scoped by the
   * applied filter values themselves: changing a filter is a different key,
   * so TanStack Query fetches/caches it independently — the mechanism
   * AC-044's "graphs update on filter change" runs on.
   */
  dashboard: (query: {
    date_from: string
    date_to: string
    category_keys: string[]
    row_mode: string
    operator_keys?: string[]
    site_keys?: string[]
  }) => ['request-management', 'dashboard', query] as const,
}
