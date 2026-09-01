import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLine, QuoteLineInput } from '@/features/quotes/types'

/**
 * The three mappers every consumer of the shared row editor needs: persisted
 * rows -> form values, form values -> wire rows, and the comparison that
 * decides whether the collection travels at all. Extracted from
 * `use-quote-form.ts`/`quote-form-payload.ts` when Gestione Richieste started
 * writing the SAME `offer_lines` (user directive 2026-08-07) — one hydration
 * and one wire projection for both modules, so a fix on one channel cannot
 * leave the other wrong.
 */

/**
 * Rebuilds persisted lines into the row-editor's nullable-per-field shape,
 * ordered by `sort_order` (AC-038).
 *
 * `withCommissions: false` omits the block entirely — Gestione Richieste
 * neither renders nor sends it, and carrying it in the form values would make
 * every save ship a `commissions` key the endpoint prohibits (and make the
 * payload diff below see a change where there is none).
 */
export function linesToFormValues(lines: QuoteLine[], withCommissions = true): QuoteLineFormValues[] {
  return lines
    .slice()
    .sort((a, b) => a.sort_order - b.sort_order)
    .map((line) => ({
      id: line.id,
      product_id: line.product_id,
      quantity: Number(line.quantity),
      // Read-only display value (spec 0088, D-5): NOT part of `QuoteLineInput`,
      // so `toLineInputs`/`sameLines` below never need to know about it.
      unit_of_measure: line.unit_of_measure,
      unit_price: Number(line.unit_price),
      vat_rate_id: line.vat_rate_id,
      ...(withCommissions
        ? {
            commissions: (line.commissions ?? []).map((commission) => ({
              ...commission,
              value: Number(commission.value),
            })),
          }
        : {}),
    }))
}

/**
 * Casts a validated row to the wire shape, assigning `sort_order` from its
 * position in the array (the contract's own fallback when the key is
 * omitted — AC-038 — so the client never needs to invent one). Every field
 * is non-null here: the schema's per-row `superRefine` (quote-schema.ts)
 * already blocks submit on an incomplete row before this ever runs.
 *
 * `commissions` travel only when the row carries them: Gestione Richieste
 * never fills that block in (the endpoint prohibits it), the Offerte form
 * always does.
 */
export function toLineInputs(rows: QuoteLineFormValues[]): QuoteLineInput[] {
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
 * Rebuilds the persisted rows into the same comparable wire shape used by
 * `toLineInputs` (decimal STRINGS coerced to numbers, D-9 contract), ordered
 * by `sort_order` (AC-038: the server already returns them that way, this
 * just makes the invariant explicit for the diff below).
 *
 * `withCommissions: false` keeps the baseline comparable with a form that
 * never carries them (see `linesToFormValues`): a channel that cannot edit
 * the block must not read it as a difference.
 */
export function originalLineInputs(lines: QuoteLine[], withCommissions = true): QuoteLineInput[] {
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
      ...(withCommissions && line.commissions
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

/** Order- and value-sensitive comparison of two line-input collections (sort_order/position carries meaning, AC-038). */
export function sameLines(a: QuoteLineInput[], b: QuoteLineInput[]): boolean {
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
 * The VAT percentages the persisted rows already carry, seeding the shared
 * cache the live preview reads (AC-071): the `vat-rates/for-select` picker
 * never exposes a percentage, only a hydrated line (or a product's `meta`)
 * does.
 */
export function vatRatePercentsFromLines(lines: QuoteLine[]): Record<number, number> {
  const entries: Record<number, number> = {}
  for (const line of lines) {
    if (line.vat_rate) {
      entries[line.vat_rate.id] = Number(line.vat_rate.rate)
    }
  }
  return entries
}
