/**
 * Contracts CRUD types (spec 0072, frozen `data_contract`). A contract is a
 * 1-1 extension of an already-`closed_won` quote: it never copies the
 * quote's client/opportunity/products/amounts/documents — every such field
 * below is a live projection through the quote's own relations, not a
 * persisted column on `contracts` (AC-040).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { QuoteLine } from '@/features/quotes/types'
import type { WorkOrderType } from '@/features/work-orders/types'

/** `App\Enums\ContractStatusGroup` (D-5): dedicated to this module, not shared with `QuoteStatusGroup`. */
export type ContractStatusGroupValue = 'open' | 'pending' | 'closed_won' | 'closed_lost'

/** A hydrated `{id, name}` relation projection (registry/opportunity/company/company_site/commercial/reporter/supervisor/payment_method/validated_by/terminated_by). */
export interface ContractRelationRef {
  id: number
  name: string
}

/** `operational_sites` has no `name` column: the site IS its composed address (mirrors `QuoteOperationalSiteRef`). */
export interface ContractOperationalSiteRef {
  id: number
  label: string
}

/** The linked contract status, as exposed by `ContractResource.contract_status` / `.status_before_suspension`. */
export interface ContractStatusRef {
  id: number
  name: string
  color: string | null
  group: ContractStatusGroupValue
}

/**
 * Minimal projection of the underlying quote (D-1: the contract shows the
 * QUOTE's code, it has none of its own). `created_at` is "quote date"
 * (D-1 fact: `quotes` has no dedicated date column).
 */
export interface ContractQuoteRef {
  id: number
  code: string
  title: string
  created_at: string
  /** decimal cast: numeric string, never a JS number (mirrors `QuoteDetail`). */
  revenue_net: string
  revenue_vat: string
  revenue_gross: string
  cost_net: string
  margin_net: string
}

/** One side of the persisted economic summary (mirrors `QuoteAmountBreakdown`). */
export interface ContractAmountBreakdown {
  net: string
  vat: string
  gross: string
}

/** The contract's read-only economic summary, projected from the quote (BR-7). */
export interface ContractSummary {
  revenue: ContractAmountBreakdown
  cost: ContractAmountBreakdown
  margin: { net: string }
}

/** Calculated at read time (D-4/BR-6), never persisted; `null` when neither threshold applies. */
export type ContractAlert = 'expiring' | 'renewal_due' | null

/** Single contract detail returned by GET/PATCH/action endpoints (envelope `data`). Matches `ContractResource`. */
export interface ContractDetail {
  id: number
  quote_id: number
  quote: ContractQuoteRef
  registry: ContractRelationRef | null
  opportunity: ContractRelationRef | null
  company: ContractRelationRef | null
  company_site: ContractRelationRef | null
  operational_site: ContractOperationalSiteRef | null
  commercial: ContractRelationRef | null
  reporter: ContractRelationRef | null
  supervisor: ContractRelationRef | null
  payment_method: ContractRelationRef | null
  contract_status_id: number
  contract_status: ContractStatusRef
  /** date, nullable; written once at creation (D-7) and never rewritten by suspend/reactivate. */
  accepted_at: string | null
  validated_at: string | null
  renewal_date: string | null
  expiry_date: string | null
  terminated_at: string | null
  termination_reason: string | null
  payment_notes: string | null
  comments: string | null
  validated_by: ContractRelationRef | null
  terminated_by: ContractRelationRef | null
  /** datetime, nullable (D-3): set by the automatic suspension, cleared by "Riattiva contratto". */
  suspended_at: string | null
  status_before_suspension: ContractStatusRef | null
  is_suspended: boolean
  alert: ContractAlert
  days_to_expiry: number | null
  days_to_renewal: number | null
  /** The quote's revenue lines, read-only (BR-7): rendered with the shared `QuoteLinesReadOnlyList`, never edited here. */
  offer_lines: QuoteLine[]
  summary: ContractSummary
  created_at: string
  updated_at: string
}

/** A `ContractDetail` carrying the actor's authorization metadata for this instance (spec 0004). */
export interface ContractDetailWithPermissions extends ContractDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for PATCH /contracts/{id} (partial, D-6: no create/delete). The
 * endpoint still accepts `contract_status_id`, but no client sends it since
 * the status left the "Modifica dati" form (user directive 2026-08-31).
 */
export interface UpdateContractPayload {
  renewal_date?: string | null
  expiry_date?: string | null
  payment_notes?: string | null
  comments?: string | null
}

/** Payload for POST /contracts/{id}/validate (BR-3). */
export interface ValidateContractPayload {
  validated_at?: string
  contract_status_id?: number
}

/**
 * One row of `GET /contracts/{id}/programmable-lines` (spec 0095 D-6): a
 * REVENUE line of the contract's offer, with its work-order occupation.
 * `product`/`unit_of_measure` are nullable per the frozen `data_contract`
 * (unlike `QuoteLine`, whose `product` is never null).
 */
export interface ContractProgrammableLineProduct {
  id: number
  code: string
  name: string
  category: { id: number; name: string } | null
}

/** The work order already using a programmable line (AC-021/060), or `null` when the line is free. */
export interface ContractProgrammableLineWorkOrderRef {
  id: number
  code: string
}

export interface ContractProgrammableLine {
  id: number
  sort_order: number
  product: ContractProgrammableLineProduct | null
  /** decimal cast: numeric string, never a JS number. */
  quantity: string
  unit_of_measure: { id: number; name: string; symbol: string } | null
  work_order: ContractProgrammableLineWorkOrderRef | null
}

/**
 * Payload for POST /contracts/{id}/work-orders (spec 0095 D-6/D-11):
 * generates ONE work order from the selected offer lines. `quote_id` is
 * never a key here — the server resolves it from the contract (constraint).
 */
export interface CreateContractWorkOrderPayload {
  title: string
  type: WorkOrderType
  quote_line_ids: number[]
}

/**
 * Payload for POST /contracts/{id}/change-status ("Modifica stato",
 * direttiva 2026-08-31 rev.2): the destination status is mandatory and must
 * belong to the `open`/`pending` groups — this action moves a contract
 * within its working phase, never across a closure.
 */
export interface ChangeContractStatusPayload {
  contract_status_id: number
}

/**
 * Payload for POST /contracts/{id}/reactivate (BR-2). `contract_status_id`
 * is mandatory when the contract is disdetto (nothing recorded the status
 * preceding the disdetta, so the user picks it) and must not belong to the
 * `closed_lost` group; on the suspended path the body is empty and the
 * pre-suspension status is restored server-side.
 */
export interface ReactivateContractPayload {
  contract_status_id?: number
}

/** Payload for POST /contracts/{id}/terminate (BR-4). `contract_status_id`, when given, must belong to group `closed_lost` (server-enforced). */
export interface TerminateContractPayload {
  terminated_at: string
  termination_reason: string
  contract_status_id?: number
}
