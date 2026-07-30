/**
 * Quotes CRUD types. The generic table types (columns/filters/actions/rows)
 * live in `features/table/types.ts`; this file holds only what is genuinely
 * quotes-specific. Source of truth: spec 0065 frozen `data_contract`.
 *
 * Decimal columns (`quantity`, `unit_price`, `net_amount`, `vat_amount`,
 * `total_amount`, the `summary` amounts, `vat_rate.rate`) are `decimal(...)`
 * casts server-side: Laravel serializes them as numeric STRINGS, never JS
 * numbers (mirrors `ProjectDetail.total_budget`/`ProductDetail.cost`). Every
 * such field is typed `string` here; convert with `Number(...)` only at the
 * point of calculation/formatting (see `quote-totals.ts`).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CommissionRole, CommissionType } from '@/features/commission-configurations/types'
import type { ModuleCreateParams } from '@/features/modules/types'

export type QuoteCommissionOrigin = 'PRODUCT' | 'PRODUCT_CATEGORY' | 'MANUAL_OVERRIDE'

export type QuoteCommissionRecipientType = 'referent' | 'user' | 'registry'

/**
 * The ONE identity a commission role may be awarded to on a given quote line,
 * as resolved server-side by `POST /quotes/commission-recipients`: the quote's
 * commercial/reporter/supervisor for the three people roles, the line
 * product's supplier for the fourth. The recipient is never picked by the
 * user — the dialog only displays it locked.
 */
export interface QuoteCommissionRecipient {
  type: QuoteCommissionRecipientType
  id: number
  name: string
}

/** `null` for a role with no upstream selection: no commission may exist for it. */
export type QuoteCommissionRecipientMap = Record<CommissionRole, QuoteCommissionRecipient | null>

/**
 * The upstream selections a line's commissions resolve their locked recipients
 * against — the quote's own role fields, read live off the open form (they may
 * differ from what is persisted until it is saved).
 */
export interface QuoteCommissionContext {
  quoteId?: number
  commercialId: number | null
  reporterId: number | null
  supervisorId: number | null
}

export interface QuoteLineCommission {
  id?: number
  recipient_role: CommissionRole
  recipient_type: QuoteCommissionRecipientType
  recipient_id: number
  recipient?: QuoteRelationRef | null
  commission_type: CommissionType
  value: string
  calculated_amount: string
  internal_note: string | null
  origin: QuoteCommissionOrigin
  commission_configuration_id: number | null
}

export type QuoteLineCommissionInput = Omit<
  QuoteLineCommission,
  'calculated_amount' | 'recipient' | 'value'
> & { value: number }
  & { recipient?: QuoteRelationRef | null }

/** A hydrated `{id, name}` relation projection (opportunity/commercial/reporter/supervisor). */
export interface QuoteRelationRef {
  id: number
  name: string
}

/**
 * The linked quote status's identity, as exposed by `QuoteResource.quote_status`.
 * The FK is NOT NULL: every quote always has a status.
 */
export interface QuoteStatusRef {
  id: number
  name: string
  color: string | null
  group: string
}

/** Minimal category/business-function projection hydrating a quote line's product (spec 0065 D-7). */
export interface QuoteLineCategoryRef {
  id: number
  name: string
}

/**
 * The product a quote line points to, resolved LIVE (D-7): `name`/`code`/
 * `category`/`business_function` always reflect the product's current state,
 * never a snapshot — only the amounts on `QuoteLine` itself are frozen (D-10).
 */
export interface QuoteLineProductRef {
  id: number
  code: string
  name: string
  category: QuoteLineCategoryRef | null
  business_function: QuoteLineCategoryRef | null
}

/** The VAT rate hydrated on a quote line, frozen at the percentage used when the row was saved (D-10). */
export interface QuoteLineVatRateRef {
  id: number
  name: string
  rate: string
}

/**
 * A single revenue (`offer_lines`) or cost (`cost_lines`) row, as exposed by
 * `QuoteResource`. Both tabs share the exact same shape (D-11): the
 * discriminant (`line_type`) lives server-side only, never on the wire.
 */
export interface QuoteLine {
  id: number
  product_id: number
  product: QuoteLineProductRef
  /** decimal(15,2) */
  quantity: string
  /** decimal(15,2) */
  unit_price: string
  vat_rate_id: number | null
  vat_rate: QuoteLineVatRateRef | null
  /** `quantity * unit_price`, rounded half-up to 2 decimals (D-12). */
  net_amount: string
  /** `net_amount * rate / 100`, rounded half-up to 2 decimals; 0 when `vat_rate_id` is null. */
  vat_amount: string
  /** `net_amount + vat_amount`. */
  total_amount: string
  sort_order: number
  commissions?: QuoteLineCommission[]
}

/** One side (`revenue`/`cost`) of the persisted economic summary (D-9). */
export interface QuoteAmountBreakdown {
  net: string
  vat: string
  gross: string
}

/**
 * The quote's persisted economic summary (D-5/D-9): imponibile, IVA e totale
 * affiancati per offerta e costi, più il margine SULL'IMPONIBILE
 * (ricavi netti - costi netti, può essere negativo, D-5).
 */
export interface QuoteSummary {
  revenue: QuoteAmountBreakdown
  cost: QuoteAmountBreakdown
  margin: { net: string }
  commissions?: Record<'commercial' | 'reporter' | 'supervisor' | 'supplier', string>
}

/**
 * Single quote detail returned by GET/POST/PATCH /quotes (envelope `data`).
 * Matches `QuoteResource`. `commercial`/`reporter`/`supervisor` are a
 * SNAPSHOT (D-3): precompiled from the opportunity at creation, then freely
 * editable on the quote itself — never re-derived from the opportunity again.
 */
export interface QuoteDetail {
  id: number
  code: string
  title: string
  opportunity_id: number
  opportunity: QuoteRelationRef
  quote_status_id: number
  quote_status: QuoteStatusRef
  commercial_id: number | null
  commercial: QuoteRelationRef | null
  reporter_id: number | null
  reporter: QuoteRelationRef | null
  supervisor_id: number | null
  supervisor: QuoteRelationRef | null
  internal_notes: string | null
  offer_lines: QuoteLine[]
  cost_lines: QuoteLine[]
  summary: QuoteSummary
  created_at: string
  updated_at: string
}

/**
 * A `QuoteDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /quotes/{id}`. Used to seed the
 * edit form's `ResourcePermissionsProvider` without a second request.
 */
export interface QuoteDetailWithPermissions extends QuoteDetail {
  permissions: ResourcePermissions
}

/**
 * A revenue/cost row as sent to the server (create/update payload). Only the
 * inputs travel: `net_amount`/`vat_amount`/`total_amount` are `prohibited`
 * (AC-076/AC-033) — the server computes and freezes them (D-10/D-12).
 */
export interface QuoteLineInput {
  id?: number
  product_id: number
  /** > 0, max 999999.99, max 2 decimals. */
  quantity: number
  /** >= 0, max 99999999.99, max 2 decimals. */
  unit_price: number
  vat_rate_id?: number | null
  /** Row position; when omitted the server uses the array index. */
  sort_order?: number
  commissions?: QuoteLineCommissionInput[]
}

/**
 * Payload for POST /quotes (create). `code` mirrors the product/project
 * pattern (D-13/D-1b): omitted/empty falls back to server-side sequential
 * generation (`QUO-{seq:4}`). `commercial_id`/`reporter_id`/`supervisor_id`,
 * when omitted, are inherited from the opportunity server-side (D-3).
 */
export interface CreateQuotePayload {
  code?: string | null
  title: string
  opportunity_id: number
  quote_status_id?: number | null
  commercial_id?: number | null
  reporter_id?: number | null
  supervisor_id?: number | null
  internal_notes?: string | null
  /** Full-replace, max 200 rows (D-8/AC-035); always sent in full on create. */
  offer_lines?: QuoteLineInput[]
  cost_lines?: QuoteLineInput[]
}

/**
 * Payload for PATCH /quotes/{id} (partial update). `opportunity_id` is
 * PROHIBITED (immutable, AC-025); `code` is absent from `rules()` entirely
 * (immutable, read-only ceiling once persisted, AC-069) — both are omitted
 * from this type, not merely optional. `offer_lines`/`cost_lines`, when
 * present, REPLACE the full existing set of that type (D-8/AC-036); omitting
 * the key leaves the set untouched.
 */
export type UpdateQuotePayload = Partial<Omit<CreateQuotePayload, 'opportunity_id' | 'code'>>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and
 * `QuoteForm`. `create.params` carries a preset `opportunity_id` (spec 0067
 * AC-050) when the form opens from a context that already knows the
 * Opportunity — the `ModuleCreateParams` mechanism (spec 0045), mirroring
 * `OpportunityFormMode`'s `lead_id` via `mode.params`.
 */
export type QuoteFormMode =
  | { type: 'create'; params?: ModuleCreateParams }
  | { type: 'edit'; quote: QuoteDetailWithPermissions }
