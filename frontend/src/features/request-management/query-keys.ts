/**
 * Centralized query keys for the request-management feature (mirrors the
 * opportunities' `opportunityDetailQueryKey` fresh-on-open pattern). Rooted
 * on `moduleKey` (spec 0130 frozen `frontend_contract`: "query key root ...
 * = module.key"), so Gestione Richieste and Gestione Iscritti — same quote
 * ids, different permission/scope — never share a cache entry.
 */

import type { RequestModuleKey } from '@/features/request-management/request-module'

export const requestManagementKeys = {
  /**
   * Query key of a single work panel (fresh-on-open pattern). Shared by the
   * panel's fetch and by the post-mutation invalidation, so they can never
   * drift apart. `null` (an unparsable route param) is a key that is never
   * fetched.
   */
  panel: (moduleKey: RequestModuleKey, id: number | null) => [moduleKey, 'panel', id] as const,
  /** Query key of the Product Category tab strip (spec 0064). */
  categories: (moduleKey: RequestModuleKey) => [moduleKey, 'categories'] as const,
  /**
   * Query key of the create form's "Informazioni aggiuntive" resolution (user
   * directive 2026-08-07): the criteria themselves, so switching category and
   * back re-reads the cache instead of refetching. Create-only (D-8): the
   * caller always resolves it against `REQUEST_MODULE.key`.
   */
  formContext: (moduleKey: RequestModuleKey, criteriaKey: string) =>
    [moduleKey, 'form-context', criteriaKey] as const,
  /**
   * Query key of a category's "does it expose exactly one product?" probe
   * (user directive 2026-09-09). Its OWN key and not `forSelectKeys.list`:
   * that one belongs to the pickers' infinite query, whose cached shape is a
   * page list, not the single product resolved here.
   */
  categorySoleProduct: (moduleKey: RequestModuleKey, categoryId: number) =>
    [moduleKey, 'category-sole-product', categoryId] as const,
  /** Query key of the active category tab's resolved G.A. labels (spec 0080), the create form's own fetch. */
  categoryManagerLabels: (moduleKey: RequestModuleKey, categoryId: number | null) =>
    [moduleKey, 'category-manager-labels', categoryId] as const,
  /**
   * Query key of a CSV report run's poll (spec 0106). `null` before a run
   * exists yet — a stable, inert key `useRequestReport` disables the query on.
   */
  reportRun: (moduleKey: RequestModuleKey, reportRunId: number | null) =>
    [moduleKey, 'report', reportRunId] as const,
  /** Query key of the report's selectable branches (spec 0106 rev-2). */
  reportCategories: (moduleKey: RequestModuleKey) => [moduleKey, 'report-categories'] as const,
  /** Query key of the report's selectable GA2 operators (spec 0109). */
  reportOperators: (moduleKey: RequestModuleKey) => [moduleKey, 'report-operators'] as const,
  /** Query key of the report's selectable operational sites (spec 0112). */
  reportSites: (moduleKey: RequestModuleKey) => [moduleKey, 'report-sites'] as const,
  /**
   * Prefix of EVERY dashboard entry, whatever the filters: what a write that
   * moves an indicator (e.g. a note, "N. Telefonate Effettuate") invalidates.
   */
  dashboardAll: (moduleKey: RequestModuleKey) => [moduleKey, 'dashboard'] as const,
  /**
   * Query key of the dashboard's aggregates (spec 0107), scoped by the
   * applied filter values themselves: changing a filter is a different key,
   * so TanStack Query fetches/caches it independently — the mechanism
   * AC-044's "graphs update on filter change" runs on.
   */
  dashboard: (
    moduleKey: RequestModuleKey,
    query: {
      date_from?: string
      date_to?: string
      category_keys: string[]
      row_mode: string
      operator_keys?: string[]
      site_keys?: string[]
    },
  ) => [...requestManagementKeys.dashboardAll(moduleKey), query] as const,
}
