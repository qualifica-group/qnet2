/**
 * Units of measure CRUD types. The generic table types (columns/filters/
 * actions/rows) live in `features/table/types.ts`; this file holds only what
 * is genuinely units-of-measure-specific — the resource and its create/
 * update payloads. Source of truth: spec 0088 frozen `data_contract`. Follows
 * `vat-rates` for leanness (no `is_active`, no `sort_order`, no reorder) and
 * `payment-methods` for `code` (unique, immutable after create, D-1).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Single unit of measure detail returned by GET/POST/PATCH /units-of-measure
 * (envelope `data`). Matches `UnitOfMeasureResource`.
 */
export interface UnitOfMeasureDetail {
  id: number
  /** Snake_case identifier, unique, immutable after create (D-1). */
  code: string
  name: string
  symbol: string
  description: string | null
  created_at: string
  updated_at: string
}

/**
 * A `UnitOfMeasureDetail` carrying the actor's authorization metadata for
 * this instance (spec 0004), as returned by `GET /units-of-measure/{id}`
 * (`show`). Used to seed the edit form's `ResourcePermissionsProvider`
 * without a second request.
 */
export interface UnitOfMeasureDetailWithPermissions extends UnitOfMeasureDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /units-of-measure (create). */
export interface CreateUnitOfMeasurePayload {
  name: string
  symbol: string
  code: string
  description?: string | null
}

/**
 * Payload for PATCH /units-of-measure/{id} (partial update). Every field is
 * optional so the request only carries what actually changed. `code` is
 * PROHIBITED at the HTTP level (D-1): it is never a key of this type, so a
 * caller cannot accidentally include it.
 */
export type UpdateUnitOfMeasurePayload = Partial<Omit<CreateUnitOfMeasurePayload, 'code'>>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `UnitOfMeasureForm` component.
 */
export type UnitOfMeasureFormMode =
  | { type: 'create' }
  | { type: 'edit'; unitOfMeasure: UnitOfMeasureDetailWithPermissions }
