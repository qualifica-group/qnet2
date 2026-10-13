/**
 * InvoiceTags CRUD types. The generic table types (columns/filters/actions/rows)
 * live in `features/table/types.ts`; this file holds only what is genuinely
 * invoice-tags-specific — the resource and its create/update payloads. Source of
 * truth: spec 0247 frozen `data_contract` (D-5).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Single invoice tag detail returned by GET/POST/PATCH /invoice-tags
 * (envelope `data`). `sort_order` is server-managed: read-only, never
 * accepted on write. `color` is a palette TOKEN (never a hex) validated against a
 * server-side allow-list (`App\Support\BadgeTokens`). No default row, no icon:
 * the tag is optional on an invoice.
 */
export interface InvoiceTagDetail {
  id: number
  name: string
  description: string | null
  /** Palette token of `BADGE_COLOR_TOKENS`, required by the backend. */
  color: string
  sort_order: number
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

/**
 * A `InvoiceTagDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /invoice-tags/{id}` (`show`). Used
 * to seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface InvoiceTagDetailWithPermissions extends InvoiceTagDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /invoice-tags (create). `sort_order` is NOT
 * accepted by the backend: placement is server-managed.
 */
export interface CreateInvoiceTagPayload {
  name: string
  color: string
  description?: string | null
  is_active?: boolean
}

/**
 * Payload for PATCH /invoice-tags/{id} (partial update). Every field is optional
 * so the request only carries what actually changed.
 */
export type UpdateInvoiceTagPayload = Partial<CreateInvoiceTagPayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `InvoiceTagForm` component.
 */
export type InvoiceTagFormMode =
  | { type: 'create' }
  | { type: 'edit'; invoiceTag: InvoiceTagDetailWithPermissions }
