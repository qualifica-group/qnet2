/**
 * Quote line types: the revenue (`offer_lines`) / cost (`cost_lines`) row
 * shapes (spec 0065 D-11) and the commission block a line carries. Split out
 * of `types.ts` (engineering.md §6 size limit, spec 0144) — `types.ts`
 * re-exports every symbol below verbatim, so an existing
 * `import type { QuoteLine } from '@/features/quotes/types'` keeps working
 * unchanged.
 *
 * Decimal columns (`quantity`, `unit_price`, `net_amount`, `vat_amount`,
 * `total_amount`) are `decimal(...)` casts server-side: Laravel serializes
 * them as numeric STRINGS, never JS numbers. Every such field is typed
 * `string` here; convert with `Number(...)` only at the point of
 * calculation/formatting (see `quote-totals.ts`).
 */

import type { CommissionRole, CommissionType } from '@/features/commission-configurations/types'

export type QuoteCommissionOrigin =
  | 'PRODUCT'
  | 'PRODUCT_CATEGORY'
  | 'RECIPIENT'
  | 'MANUAL_OVERRIDE'

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

/** A hydrated `{id, name}` relation projection (opportunity/commercial/reporter/supervisor/company/company_site). */
export interface QuoteRelationRef {
  id: number
  name: string
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
  /**
   * The product's typology, read LIVE through the product (spec 0099, D-5) —
   * deliberately NOT frozen onto the line, unlike `unit_of_measure`. Seeds
   * the live summary's per-typology bucket cache in edit mode.
   */
  product_typology: QuoteLineCategoryRef | null
  business_function: QuoteLineCategoryRef | null
}

/** The VAT rate hydrated on a quote line, frozen at the percentage used when the row was saved (D-10). */
export interface QuoteLineVatRateRef {
  id: number
  name: string
  rate: string
}

/**
 * The unit of measure congelated on a quote line at write time (spec 0088,
 * D-5): a snapshot, unlike `product`/`vat_rate` which read live. `null` only
 * when the backend cannot resolve any unit (should not happen in practice —
 * every product carries one, D-4).
 */
export interface QuoteLineUnitOfMeasureRef {
  id: number
  name: string
  symbol: string
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
  /**
   * Congelated at write time from the product's own unit (spec 0088, D-5);
   * a row saved before this field existed falls back server-side to the
   * product's CURRENT unit (AC-053). Read-only: never part of the write
   * payload (`QuoteLineInput`), the backend rejects it with 422 if sent.
   */
  unit_of_measure: QuoteLineUnitOfMeasureRef | null
  /** Free text the operator adds to this line, printable in the quote document (`additional_description` column key). */
  additional_description: string | null
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
  /**
   * Spec 0144 (D-2): the id of the REVENUE row this COST row is imputed to,
   * `null` for a generic cost; always `null` on a REVENUE row. Optional for
   * the same fixture-compatibility reason as `QuoteDetail`'s own additive
   * fields (`types.ts`) — a missing key reads the same as `null`.
   */
  offer_line_id?: number | null
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
  /** Omitted = the server keeps the stored value; `null` clears it. */
  additional_description?: string | null
  /** Row position; when omitted the server uses the array index. */
  sort_order?: number
  commissions?: QuoteLineCommissionInput[]
  /**
   * Spec 0144 (D-4), COST rows only: id of a persisted REVENUE row of this
   * offer. Mutually exclusive with `offer_line_index`; both omitted = generic
   * cost. `prohibited` on an `offer_lines` row.
   */
  offer_line_id?: number
  /**
   * Spec 0144 (D-4), COST rows only: 0-based position in the `offer_lines`
   * array of the SAME request, for a REVENUE row not yet persisted.
   * `prohibited` on an `offer_lines` row.
   */
  offer_line_index?: number
}
