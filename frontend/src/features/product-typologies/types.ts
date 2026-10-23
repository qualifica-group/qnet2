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
 * Which proforma request the lines of this typology feed (spec 0247 D-6);
 * null = never billed through a proforma request. Mirrors the backend
 * `ProformaRequestKind` enum.
 */
export type ProformaKind = 'consultancy' | 'institution'

/** Ordered options for the proforma kind picker (after "none"). */
export const PROFORMA_KINDS: readonly ProformaKind[] = ['consultancy', 'institution']

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
  /** Badge colour token (`BADGE_COLOR_TOKENS`), required (spec 0204 D-3). */
  color: string
  /** Whether the Supplier commission is calculated for lines of this typology (spec 0202; the direction moved to the commission rule, spec 0306 D-2). */
  supplier_commission_enabled: boolean
  /** Proforma request the lines of this typology feed, null = none (spec 0247 D-6). */
  proforma_kind: ProformaKind | null
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
  color: string
  supplier_commission_enabled: boolean
  proforma_kind: ProformaKind | null
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
