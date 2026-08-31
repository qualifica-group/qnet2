/**
 * Shared types for the reusable `for-select` contract (ADR 0011). Any
 * entity-backed select on this backend speaks this exact shape, so these types
 * are domain-agnostic and reused by every concrete for-select feature.
 */

/** Reuse the canonical pagination/envelope shapes already defined for lists. */
export type {
  Pagination,
  PaginatedResponse,
} from '@/features/notifications/types'

/**
 * Minimal projection of an entity option as returned by
 * `GET /api/{resource}/for-select`. `id` + `label` are always present;
 * `subtitle` is the optional secondary line (e.g. the user email). A
 * concrete resource with a richer presentation bag extends this with its own
 * typed `meta` (see `ProjectForSelectItem`, `OperationalSiteForSelectItem`,
 * ...) rather than widening this base shape.
 */
export interface ForSelectItem {
  id: number
  label: string
  subtitle?: string | null
  /**
   * Optional avatar image (data: URI or URL). Present only for entities that
   * project one (e.g. users). Rendered by the select when `showAvatar` is set;
   * a null/absent value falls back to the label's initials.
   */
  avatar_url?: string | null
}

/** Query parameters accepted by every for-select endpoint. */
export interface ForSelectParams {
  /** Server-side case-insensitive search term. */
  search?: string
  /** Pagination offset (rows to skip). Default 0. */
  offset?: number
  /** Page size. Default 25, capped server-side at 100. */
  limit?: number
  /**
   * Ids of already-selected values to hydrate (edit mode). Returned in addition
   * to the searched page, deduplicated, and NOT counted in `pagination.total`.
   */
  ids?: number[]
  /**
   * Extra, resource-specific query parameters (spec 0032 `dependency.param`):
   * a parent filter's value forwarded to a dependent field's for-select
   * request (e.g. `{ project_id: 12 }` to scope a campaign picker). Omitted
   * for plain for-selects with no dependency. An array value is serialized
   * as repeated `key[]=` params (Laravel convention) — e.g.
   * `{ category_ids: [3, 7] }` scoping the products picker.
   */
  params?: Record<string, string | number | string[] | number[]>
}
