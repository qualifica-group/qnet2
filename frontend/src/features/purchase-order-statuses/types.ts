/**
 * ODA status catalog types. Source of truth: spec 0248 frozen `data_contract`.
 * The rows are the 7 system rows of the `PurchaseOrderStatus` enum: only the
 * presentation (name, color, icon) is editable, the order moves through the
 * reorder endpoint, and there is no create nor delete.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** Resource segment shared by the table domain, the for-select and the reorder endpoints. */
export const PURCHASE_ORDER_STATUSES_RESOURCE = 'purchase-order-statuses'

/**
 * Single ODA status returned by GET/PATCH /purchase-order-statuses/{id}
 * (envelope `data`). `system_key` is the enum value the logic runs on and
 * `sort_order` is server-managed: both read-only. `color` is a palette TOKEN
 * and `icon` a curated lucide name, both validated server-side.
 */
export interface PurchaseOrderStatusDetail {
  id: number
  system_key: string
  name: string
  color: string
  icon: string | null
  sort_order: number
}

/** A detail carrying the actor's authorization metadata (spec 0004), as returned by `show`/`update`. */
export interface PurchaseOrderStatusDetailWithPermissions extends PurchaseOrderStatusDetail {
  permissions: ResourcePermissions
}

/** Payload for PATCH /purchase-order-statuses/{id}: `system_key` and `sort_order` are never keys (422). */
export interface UpdatePurchaseOrderStatusPayload {
  name?: string
  color?: string
  icon?: string | null
}
