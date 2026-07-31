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
   * Query key of the create form's context preview (user directive
   * 2026-07-31), keyed on the criteria that resolve it: the same source +
   * product lines produce the same statuses/attributes/layout, so typing back
   * and forth between two categories re-reads the cache instead of the network.
   */
  formContext: (sourceId: number | null, categoryPairs: string) =>
    ['request-management', 'form-context', sourceId, categoryPairs] as const,
}
