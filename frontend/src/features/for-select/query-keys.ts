/**
 * Query keys for the reusable for-select infinite queries. Keyed by resource and
 * the active search term so each entity's option list caches independently and
 * a search change starts a fresh paginated query. The selected `ids` are NOT
 * part of the key: they only hydrate the first page and must not fragment the
 * cache per selection.
 */
export const forSelectKeys = {
  all: ['for-select'] as const,
  resource: (resource: string) => ['for-select', resource] as const,
  /**
   * `params` (spec 0032 `dependency.param`) only joins the key when actually
   * supplied, so the common, param-less for-select keeps its original,
   * unchanged key shape.
   */
  list: (resource: string, search: string, params?: Record<string, string | number | string[] | number[]>) =>
    params && Object.keys(params).length > 0
      ? (['for-select', resource, { search, params }] as const)
      : (['for-select', resource, { search }] as const),
  /**
   * Label-hydration key for an explicit id set (edit-mode / off-page selections).
   * Unlike `list`, the ids ARE part of this key: each selection resolves its own
   * cache entry so sibling selects sharing a resource never collide on a single,
   * ids-less list entry (which would let only one instance's ids reach the
   * server, leaving the others showing `#id`). Ids are pre-sorted by the caller
   * so key identity is order-independent.
   */
  labels: (resource: string, ids: number[], params?: Record<string, string | number | string[] | number[]>) =>
    params && Object.keys(params).length > 0
      ? (['for-select', resource, 'labels', { ids, params }] as const)
      : (['for-select', resource, 'labels', { ids }] as const),
}
