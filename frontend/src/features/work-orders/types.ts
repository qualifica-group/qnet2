/**
 * Work orders ("Commesse") CRUD types. The generic table types (columns/
 * filters/actions/rows) live in `features/table/types.ts`; this file holds
 * only what is genuinely work-orders-specific. Source of truth: spec 0093
 * frozen `data_contract` (`WorkOrderDetail`).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** `App\Enums\WorkOrderType` (D-10): "Lavorazione" / "Progetto" live only in i18n. */
export type WorkOrderType = 'processing' | 'project'

/** `App\Enums\WorkOrderStatus` value, calculated in read (D-3), never persisted. */
export type WorkOrderStatusValue = 'open' | 'closed'

/** The status badge shape: calculated `value` plus the one input driving it. */
export interface WorkOrderStatus {
  value: WorkOrderStatusValue
  is_force_closed: boolean
}

/** A user projection for the Responsabili set (spec 0096, D-1). */
export interface WorkOrderSupervisor {
  id: number
  name: string
}

/**
 * One Partecipante (spec 0096, D-3) with its 1-based slot `position`; gaps in
 * the sequence are meaningful and reconstructed by `managerSlotsFromRefs`.
 */
export interface WorkOrderParticipant {
  id: number
  name: string
  position: number
}

/** `quote.code`/`quote.title` projection, hydrated for the "Offerta collegata" field/link (D-2). */
export interface WorkOrderQuoteRef {
  id: number
  code: string
  title: string
}

/** A quote line's product live identity (D-8: a quote line has no description of its own). */
export interface WorkOrderQuoteLineProduct {
  id: number
  code: string
  name: string
}

/** One `quote_line_work_order` pivot row, projected through its `quote_line`. */
export interface WorkOrderQuoteLine {
  id: number
  sort_order: number
  product: WorkOrderQuoteLineProduct | null
}

/**
 * Single work order detail returned by GET/POST/PATCH /work-orders (envelope
 * `data`). Matches `WorkOrderResource`.
 */
export interface WorkOrderDetail {
  id: number
  /** `COM-0001`, unique, immutable after create (D-1). */
  code: string
  title: string
  type: WorkOrderType
  status: WorkOrderStatus
  is_force_closed: boolean
  force_close_reason: string | null
  /** `Y-m-d` (spec 0096): a commessa always has a start date. */
  start_date: string
  callback_date: string | null
  /** "Responsabili": at least one, ordered by name server-side. */
  supervisors: WorkOrderSupervisor[]
  /** "Partecipanti": the ordered team, gaps preserved via `position`. */
  participants: WorkOrderParticipant[]
  description: string | null
  internal_notes: string | null
  /** = `quote.code`, derived, read-only (D-2). */
  contract_number: string | null
  quote: WorkOrderQuoteRef | null
  quote_lines: WorkOrderQuoteLine[]
  created_at: string
  updated_at: string
}

/**
 * A `WorkOrderDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /work-orders/{id}` (`show`). Used
 * to seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface WorkOrderDetailWithPermissions extends WorkOrderDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /work-orders (create). */
export interface CreateWorkOrderPayload {
  code?: string | null
  quote_id: number
  title: string
  type: WorkOrderType
  start_date: string
  supervisor_ids: number[]
  callback_date?: string | null
  description?: string | null
  internal_notes?: string | null
  is_force_closed?: boolean
  force_close_reason?: string | null
  quote_line_ids?: number[]
  /** Ordered, gap-aware slots; `null` is a deliberately empty one. */
  participant_slots?: (number | null)[]
}

/**
 * Payload for PATCH /work-orders/{id} (partial update). `code`/`quote_id` are
 * PROHIBITED at the HTTP level (D-1/D-5): neither is ever a key of this type,
 * so a caller cannot accidentally include it.
 */
export type UpdateWorkOrderPayload = Partial<Omit<CreateWorkOrderPayload, 'code' | 'quote_id'>>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `WorkOrderForm` component.
 */
export type WorkOrderFormMode =
  | { type: 'create' }
  | { type: 'edit'; workOrder: WorkOrderDetailWithPermissions }
