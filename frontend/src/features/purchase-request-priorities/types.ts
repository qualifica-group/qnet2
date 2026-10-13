/**
 * PurchaseRequestPriorities CRUD types. The generic table types (columns/filters/actions/rows)
 * live in `features/table/types.ts`; this file holds only what is genuinely
 * purchase-request-priorities-specific — the resource and its create/update payloads. Source of
 * truth: spec 0247 frozen `data_contract` (D-4).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Single RDA priority detail returned by GET/POST/PATCH /purchase-request-priorities
 * (envelope `data`). `sort_order` is server-managed: read-only, never
 * accepted on write. `color` is a palette TOKEN (never a hex) and `icon` a
 * curated kebab-case lucide name, both validated against a server-side
 * allow-list (`App\Support\BadgeTokens`).
 */
export interface PurchaseRequestPriorityDetail {
  id: number
  name: string
  description: string | null
  /** Palette token of `BADGE_COLOR_TOKENS`, required by the backend. */
  color: string
  /** Curated lucide name of `ICON_NAMES`, or null when unset. */
  icon: string | null
  sort_order: number
  is_active: boolean
  /**
   * Spec 0247 D-3: at most one row per catalog carries `true` — setting it on
   * one clears it on every other row server-side. Precompiles the RDA
   * form's own field when creation omits it; an inactive default 422s.
   */
  is_default: boolean
  created_at: string | null
  updated_at: string | null
}

/**
 * A `PurchaseRequestPriorityDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /purchase-request-priorities/{id}` (`show`). Used
 * to seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface PurchaseRequestPriorityDetailWithPermissions extends PurchaseRequestPriorityDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /purchase-request-priorities (create). `sort_order` is NOT
 * accepted by the backend: placement is server-managed.
 */
export interface CreatePurchaseRequestPriorityPayload {
  name: string
  color: string
  icon?: string | null
  description?: string | null
  is_active?: boolean
  /** Spec 0247 D-3. */
  is_default?: boolean
}

/**
 * Payload for PATCH /purchase-request-priorities/{id} (partial update). Every field is optional
 * so the request only carries what actually changed.
 */
export type UpdatePurchaseRequestPriorityPayload = Partial<CreatePurchaseRequestPriorityPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `PurchaseRequestPriorityForm` component.
 */
export type PurchaseRequestPriorityFormMode =
  | { type: 'create' }
  | { type: 'edit'; purchaseRequestPriority: PurchaseRequestPriorityDetailWithPermissions }
