/**
 * Purchase request line status catalog types (spec 0248). Source of truth: the
 * frozen `data_contract`. The catalog holds only the 6 system rows (one per
 * `PurchaseRequestLineStatus` enum value): they can be renamed, recolored,
 * given an icon and reordered, never created or deleted.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * A line status as returned by GET/PATCH /purchase-request-line-statuses/{id}
 * (envelope `data`). `system_key` (the enum value) and `sort_order` are
 * read-only: the backend 422s on their mere presence in a write. `color` is a
 * palette TOKEN and `icon` a curated lucide name (`App\Support\BadgeTokens`).
 */
export interface PurchaseRequestLineStatusDetail {
  id: number
  system_key: string
  name: string
  color: string
  icon: string | null
  sort_order: number
}

/** The detail carrying the actor's authorization metadata (spec 0004). */
export interface PurchaseRequestLineStatusDetailWithPermissions extends PurchaseRequestLineStatusDetail {
  permissions: ResourcePermissions
}

/** Payload for PATCH /purchase-request-line-statuses/{id}: only what changed. */
export interface UpdatePurchaseRequestLineStatusPayload {
  name?: string
  color?: string
  icon?: string | null
}
