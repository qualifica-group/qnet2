/**
 * Payment methods CRUD types. The generic table types (columns/filters/
 * actions/rows) live in `features/table/types.ts`; this file holds only what
 * is genuinely payment-methods-specific — the resource and its create/update
 * payloads. Source of truth: spec 0068 frozen `data_contract`. Cloned from
 * `reward-statuses` with `code` (immutable after create, D-3),
 * `payment_instructions` and `payment_days` replacing `color`, and no
 * `system_key` (no consumer exists yet, D-2). `sort_order` is server-managed
 * (D-1) and never accepted on write.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Single payment method detail returned by GET/POST/PATCH /payment-methods
 * (envelope `data`). Matches `PaymentMethodResource`.
 */
export interface PaymentMethodDetail {
  id: number
  name: string
  /** Snake_case identifier, the ONLY unique field, immutable after create (D-3). */
  code: string
  /** Fiscal/legacy classification code (e.g. "MP01"), non-unique. */
  payment_method_code: string | null
  description: string | null
  payment_instructions: string | null
  payment_days: number | null
  sort_order: number
  is_active: boolean
  created_at: string
  updated_at: string
}

/**
 * A `PaymentMethodDetail` carrying the actor's authorization metadata for
 * this instance (spec 0004), as returned by `GET /payment-methods/{id}`
 * (`show`). Used to seed the edit form's `ResourcePermissionsProvider`
 * without a second request.
 */
export interface PaymentMethodDetailWithPermissions extends PaymentMethodDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /payment-methods (create). `sort_order` is NOT accepted
 * (D-1): placement is automatic.
 */
export interface CreatePaymentMethodPayload {
  name: string
  code: string
  payment_method_code?: string | null
  description?: string | null
  payment_instructions?: string | null
  payment_days?: number | null
  is_active?: boolean
}

/**
 * Payload for PATCH /payment-methods/{id} (partial update). Every field is
 * optional so the request only carries what actually changed. `code` is
 * PROHIBITED at the HTTP level (D-3): it is never a key of this type, so a
 * caller cannot accidentally include it.
 */
export type UpdatePaymentMethodPayload = Partial<Omit<CreatePaymentMethodPayload, 'code'>>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `PaymentMethodForm` component.
 */
export type PaymentMethodFormMode =
  | { type: 'create' }
  | { type: 'edit'; paymentMethod: PaymentMethodDetailWithPermissions }
