/**
 * Contracts CRUD types (spec 0072, frozen `data_contract`). A contract is a
 * 1-1 extension of an already-`closed_won` quote: it never copies the
 * quote's client/opportunity/products/amounts/documents — every such field
 * below is a live projection through the quote's own relations, not a
 * persisted column on `contracts` (AC-040).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { QuoteLine } from '@/features/quotes/types'

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

/** Payload for PATCH /contracts/{id} (partial, D-6: no create/delete). */
export interface UpdateContractPayload {
  contract_status_id?: number
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

/** Payload for POST /contracts/{id}/schedule. `contract_status_id` is mandatory (D-2: "Programmato" is not resolvable by system_key). */
export interface ScheduleContractPayload {
  expiry_date: string
  renewal_date?: string | null
  contract_status_id: number
}

/** Payload for POST /contracts/{id}/terminate (BR-4). `contract_status_id`, when given, must belong to group `closed_lost` (server-enforced). */
export interface TerminateContractPayload {
  terminated_at: string
  termination_reason: string
  contract_status_id?: number
}
