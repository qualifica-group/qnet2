import type { QuoteFormValues, QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type {
  CreateQuotePayload,
  QuoteDetail,
  QuoteLine,
  QuoteLineInput,
  UpdateQuotePayload,
} from '@/features/quotes/types'

/**
 * Casts a validated row to the wire shape, assigning `sort_order` from its
 * position in the array (the contract's own fallback when the key is
 * omitted — AC-038 — so the client never needs to invent one). Every field
 * is non-null here: the schema's per-row `superRefine` (quote-schema.ts)
 * already blocks submit on an incomplete row before this ever runs.
 */
function toLineInputs(rows: QuoteLineFormValues[]): QuoteLineInput[] {
  return rows.map((row, index) => ({
    ...(row.id ? { id: row.id } : {}),
    product_id: row.product_id as number,
    quantity: row.quantity as number,
    unit_price: row.unit_price as number,
    vat_rate_id: row.vat_rate_id,
    sort_order: index,
    ...(row.commissions
      ? { commissions: row.commissions.map((commission) => ({
          id: commission.id,
          recipient_role: commission.recipient_role,
          recipient_type: commission.recipient_type,
          recipient_id: commission.recipient_id,
          commission_type: commission.commission_type,
          value: commission.value,
          internal_note: commission.internal_note,
          origin: commission.origin,
          commission_configuration_id: commission.commission_configuration_id,
        })) }
      : {}),
  }))
}

/**
 * Builds the create payload. `code` is included only when set (trimmed,
 * non-empty) — an empty/absent value falls back to server-side sequential
 * generation (D-13/AC-066, mirrors `projects`' `buildCreatePayload`).
 * `offer_lines`/`cost_lines` are ALWAYS sent in full (even empty): the create
 * request has no "original" set to diff against. AC-076: only the contract's
 * own `QuoteLineInput` fields are emitted — `net_amount`/`vat_amount`/
 * `total_amount` never travel, by construction of `toLineInputs`.
 */
export function buildCreatePayload(values: QuoteFormValues): CreateQuotePayload {
  const code = values.code.trim()
  return {
    ...(code ? { code } : {}),
    title: values.title,
    opportunity_id: values.opportunity_id as number,
    quote_status_id: values.quote_status_id,
    commercial_id: values.commercial_id,
    reporter_id: values.reporter_id,
    supervisor_id: values.supervisor_id,
    internal_notes: values.internal_notes,
    offer_lines: toLineInputs(values.offer_lines),
    cost_lines: toLineInputs(values.cost_lines),
  }
}

/** Order- and value-sensitive comparison of two line-input collections (sort_order/position carries meaning, AC-038). */
function sameLines(a: QuoteLineInput[], b: QuoteLineInput[]): boolean {
  if (a.length !== b.length) {
    return false
  }
  return a.every((line, index) => {
    const other = b[index]
    return (
      line.product_id === other.product_id &&
      line.quantity === other.quantity &&
      line.unit_price === other.unit_price &&
      (line.vat_rate_id ?? null) === (other.vat_rate_id ?? null)
      && JSON.stringify(line.commissions ?? []) === JSON.stringify(other.commissions ?? [])
    )
  })
}

/**
 * Rebuilds the persisted rows into the same comparable wire shape used by
 * `toLineInputs` (decimal STRINGS coerced to numbers, D-9 contract), ordered
 * by `sort_order` (AC-038: the server already returns them that way, this
 * just makes the invariant explicit for the diff below).
 */
function originalLineInputs(lines: QuoteLine[]): QuoteLineInput[] {
  return lines
    .slice()
    .sort((a, b) => a.sort_order - b.sort_order)
    .map((line, index) => ({
      id: line.id,
      product_id: line.product_id,
      quantity: Number(line.quantity),
      unit_price: Number(line.unit_price),
      vat_rate_id: line.vat_rate_id,
      sort_order: index,
      ...(line.commissions
        ? {
            commissions: line.commissions.map((commission) => ({
              id: commission.id,
              recipient_role: commission.recipient_role,
              recipient_type: commission.recipient_type,
              recipient_id: commission.recipient_id,
              commission_type: commission.commission_type,
              value: Number(commission.value),
              internal_note: commission.internal_note,
              origin: commission.origin,
              commission_configuration_id: commission.commission_configuration_id,
            })),
          }
        : {}),
    }))
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original quote (sparse diff, mirrors `projects`/`opportunities`).
 * `opportunity_id` and `code` are NEVER part of the update shape at all
 * (immutable, `UpdateQuotePayload` omits both entirely — AC-025/AC-069).
 * `offer_lines`/`cost_lines` are included only when the row SET actually
 * changed (D-8: an included key is a full-replace, so an unchanged tab must
 * stay omitted to leave the persisted set untouched, AC-036/037).
 */
export function buildUpdatePayload(values: QuoteFormValues, original: QuoteDetail): UpdateQuotePayload {
  const payload: UpdateQuotePayload = {}

  if (values.title !== original.title) {
    payload.title = values.title
  }
  if (values.quote_status_id !== original.quote_status_id) {
    payload.quote_status_id = values.quote_status_id
  }
  if (values.commercial_id !== original.commercial_id) {
    payload.commercial_id = values.commercial_id
  }
  if (values.reporter_id !== original.reporter_id) {
    payload.reporter_id = values.reporter_id
  }
  if (values.supervisor_id !== original.supervisor_id) {
    payload.supervisor_id = values.supervisor_id
  }
  if (values.internal_notes !== original.internal_notes) {
    payload.internal_notes = values.internal_notes
  }

  const offerLines = toLineInputs(values.offer_lines)
  if (!sameLines(offerLines, originalLineInputs(original.offer_lines))) {
    payload.offer_lines = offerLines
  }
  const costLines = toLineInputs(values.cost_lines)
  if (!sameLines(costLines, originalLineInputs(original.cost_lines))) {
    payload.cost_lines = costLines
  }

  return payload
}
