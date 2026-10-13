/**
 * VAT rates CRUD types. The generic table types (columns/filters/actions/
 * rows) live in `features/table/types.ts`; this file holds only what is
 * genuinely vat-rates-specific — the resource and its create/update
 * payloads. Mirrors `features/sources/types.ts` with the extra `rate` field.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * Single VAT rate detail returned by GET/POST/PATCH /vat-rates (envelope
 * `data`). Matches `VatRateResource`.
 */
export interface VatRateDetail {
  id: number
  /** Legacy id; sent only to an actor allowed to see it (`legacy-ids.view`). */
  old_id?: number | null
  name: string
  rate: number
  created_at: string
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `VatRateDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /vat-rates/{id}` (`show`). Used
 * to seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface VatRateDetailWithPermissions extends VatRateDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /vat-rates (create). */
export interface CreateVatRatePayload {
  name: string
  rate: number
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /vat-rates/{id} (partial update). Every field is
 * optional so the request only carries what actually changed.
 */
export type UpdateVatRatePayload = Partial<CreateVatRatePayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `VatRateForm` component (mirrors `SourceFormMode`).
 */
export type VatRateFormMode =
  | { type: 'create' }
  | { type: 'edit'; vatRate: VatRateDetailWithPermissions }
