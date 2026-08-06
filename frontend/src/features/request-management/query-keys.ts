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
  /** Query key of the active category tab's resolved G.A. labels (spec 0080), the create form's own fetch. */
  categoryManagerLabels: (categoryId: number | null) =>
    ['request-management', 'category-manager-labels', categoryId] as const,
}
