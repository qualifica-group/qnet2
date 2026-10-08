/**
 * Work order payment statuses CRUD types. The generic table types (columns/
 * filters/actions/rows) live in `features/table/types.ts`; this file holds
 * only what is specific to this lookup: the resource and its create/update
 * payloads. Source of truth: spec 0201 frozen `data_contract`. Same shape as
 * `reward-statuses` minus `group`/`system_key`, plus `allows_delivery`
 * (whether a line in this status can be delivered, which notifies the work
 * order supervisors/participants on transition). `color` is REQUIRED and
 * `sort_order` is server-managed (read-only, never sent on write).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** Single status returned by GET/POST/PATCH /work-order-payment-statuses (envelope `data`). */
export interface WorkOrderPaymentStatusDetail {
  id: number
  name: string
  description: string | null
  /** Palette token (e.g. "blue"), always set. */
  color: string
  sort_order: number
  is_active: boolean
  allows_delivery: boolean
  created_at: string
  updated_at: string
}

/**
 * A `WorkOrderPaymentStatusDetail` carrying the actor's authorization
 * metadata (spec 0004), as returned by `GET /work-order-payment-statuses/{id}`.
 */
export interface WorkOrderPaymentStatusDetailWithPermissions extends WorkOrderPaymentStatusDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /work-order-payment-statuses (create). */
export interface CreateWorkOrderPaymentStatusPayload {
  name: string
  description?: string | null
  color: string
  is_active?: boolean
  allows_delivery?: boolean
}

/** Payload for PATCH /work-order-payment-statuses/{id}: only the changed fields. */
export type UpdateWorkOrderPaymentStatusPayload = Partial<CreateWorkOrderPaymentStatusPayload>

/** Discriminated form mode shared by the form hook/meta-resolver and the form component. */
export type WorkOrderPaymentStatusFormMode =
  | { type: 'create' }
  | { type: 'edit'; workOrderPaymentStatus: WorkOrderPaymentStatusDetailWithPermissions }
