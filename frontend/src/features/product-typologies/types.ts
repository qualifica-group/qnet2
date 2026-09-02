/**
 * Product typologies CRUD types. The generic table types (columns/filters/
 * actions/rows) live in `features/table/types.ts`; this file holds only what
 * is genuinely product-typologies-specific — the resource and its create/
 * update payloads. Source of truth: spec 0099 frozen `data_contract`. Mirrors
 * `units-of-measure` for leanness (no `is_active`, no `sort_order`, no
 * reorder) and for `code` (unique, immutable after create, D-2).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

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
