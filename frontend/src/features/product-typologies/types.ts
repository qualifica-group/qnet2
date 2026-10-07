/**
 * Product typologies CRUD types. The generic table types (columns/filters/
 * actions/rows) live in `features/table/types.ts`; this file holds only what
 * is genuinely product-typologies-specific — the resource and its create/
 * update payloads. Source of truth: spec 0099 frozen `data_contract`. Mirrors
 * `units-of-measure` for leanness (no `is_active`, no `sort_order`, no
 * reorder) and for `code` (unique, immutable after create, D-2).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** Direction of the Supplier commission (spec 0202 D-2): earned by us or paid to the supplier. */
export type SupplierCommissionDirection = 'RECEIVED' | 'PAID'

/** Ordered options for the direction picker. */
export const SUPPLIER_COMMISSION_DIRECTIONS: readonly SupplierCommissionDirection[] = [
  'RECEIVED',
  'PAID',
]

/**
 * Single product typology detail returned by GET/POST/PATCH /product-typologies
 * (envelope `data`). Matches `ProductTypologyResource`.
 */
export interface ProductTypologyDetail {
  id: number
  /** Snake_case identifier, unique, immutable after create (D-2). */
  code: string
  name: string
  description: string | null
  /** Whether the Supplier commission is calculated for lines of this typology (spec 0202). */
  supplier_commission_enabled: boolean
  /** Required when enabled, null when disabled (spec 0202 D-7). */
  supplier_commission_direction: SupplierCommissionDirection | null
  created_at: string
  updated_at: string
}

/**
 * A `ProductTypologyDetail` carrying the actor's authorization metadata for
 * this instance (spec 0004), as returned by `GET /product-typologies/{id}`
 * (`show`). Used to seed the edit form's `ResourcePermissionsProvider`
 * without a second request.
 */
export interface ProductTypologyDetailWithPermissions extends ProductTypologyDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /product-typologies (create). */
export interface CreateProductTypologyPayload {
  name: string
  code: string
  description?: string | null
  supplier_commission_enabled: boolean
  supplier_commission_direction: SupplierCommissionDirection | null
}

/**
 * Payload for PATCH /product-typologies/{id} (partial update). Every field is
 * optional so the request only carries what actually changed. `code` is
 * PROHIBITED at the HTTP level (D-1): it is never a key of this type, so a
 * caller cannot accidentally include it.
 */
export type UpdateProductTypologyPayload = Partial<Omit<CreateProductTypologyPayload, 'code'>>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `ProductTypologyForm` component.
 */
export type ProductTypologyFormMode =
  | { type: 'create' }
  | { type: 'edit'; productTypology: ProductTypologyDetailWithPermissions }
