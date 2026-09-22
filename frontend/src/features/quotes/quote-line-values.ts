import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLine, QuoteLineInput } from '@/features/quotes/types'

/**
 * The deterministic client-only key a PERSISTED row hydrates onto (spec 0144
 * D-7): derived from the id both when the row itself needs one
 * (`linesToFormValues`) and when a COST row must point back at its
 * associated OFFER row (`line.offer_line_id` resolves to the very same
 * scheme), so no separate lookup table is needed to bridge the two.
 */
export function lineClientKey(id: number): string {
  return `line-${id}`
}

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
 * payload diff below see a change where there is none). The same flag omits
 * `additional_description`, which only the Offerte form edits: without the
 * key the server keeps the stored text.
 */
export function linesToFormValues(lines: QuoteLine[], withCommissions = true): QuoteLineFormValues[] {
  return lines
    .slice()
    .sort((a, b) => a.sort_order - b.sort_order)
    .map((line) => ({
      id: line.id,
      // Spec 0144 D-7: derived from the persisted id so a COST row's
      // `offer_line_key` (below) can point at it with no separate lookup.
      client_key: lineClientKey(line.id),
      product_id: line.product_id,
      quantity: Number(line.quantity),
      // Read-only display value (spec 0088, D-5): NOT part of `QuoteLineInput`,
      // so `toLineInputs`/`sameLines` below never need to know about it.
      unit_of_measure: line.unit_of_measure,
      unit_price: Number(line.unit_price),
      vat_rate_id: line.vat_rate_id,
      // Spec 0144 D-1/D-4: `null` on every REVENUE row (D-2) and on a COST
      // row with no association; a real association derives the SAME
      // `line-<id>` scheme from the referenced OFFER row's own id.
      offer_line_key: line.offer_line_id !== null && line.offer_line_id !== undefined
        ? lineClientKey(line.offer_line_id)
        : null,
      ...(withCommissions
        ? {
            additional_description: line.additional_description ?? null,
            commissions: (line.commissions ?? []).map((commission) => ({
              ...commission,
              value: Number(commission.value),
            })),
          }
        : {}),
    }))
}

/**
 * A row nobody has touched: every editable field still empty and no persisted
 * `id`. Since directive 2026-09-01 the create forms OPEN on one such row
 * (`EMPTY_LINE_ROW`) instead of an empty grid, so "untouched" must mean
 * "no row at all" on both sides of the submit — the schema skips its
 * `required` rules on it (quote-schema.ts) and `toLineInputs` drops it below.
 * Without this an offer-less quote/richiesta, which both endpoints accept,
 * would become unsavable unless the user deleted the seeded row by hand.
 */
export function isPristineLineRow(row: QuoteLineFormValues): boolean {
  return (
    row.id === undefined &&
    row.product_id === null &&
    row.quantity === null &&
    row.unit_price === null &&
    row.vat_rate_id === null &&
    !row.additional_description &&
    (row.commissions?.length ?? 0) === 0
  )
}

/**
 * Casts a validated row to the wire shape, assigning `sort_order` from its
 * position in the array (the contract's own fallback when the key is
 * omitted — AC-038 — so the client never needs to invent one). Untouched
 * rows are dropped first (see `isPristineLineRow`); of what remains every
 * field is non-null, since the schema's per-row `superRefine`
 * (quote-schema.ts) already blocks submit on an incomplete row.
 *
 * `additional_description` travels only when the row carries the key (an
 * absent key keeps the stored value server-side); blank text is sent as null.
 *
 * `commissions` travel only when the row carries them: Gestione Richieste
 * never fills that block in (the endpoint prohibits it), the Offerte form
 * always does.
 *
 * `resolveOfferLineReference` (spec 0144 D-4), COST rows only: resolves the
 * row's client-only `offer_line_key` into the wire's `offer_line_id`/
 * `offer_line_index` — see `offerLineReferenceResolver` below. Omitted for
 * `offer_lines` itself, whose rows never carry either key (prohibited).
 */
export function toLineInputs(
  rows: QuoteLineFormValues[],
  resolveOfferLineReference?: (row: QuoteLineFormValues) => Pick<QuoteLineInput, 'offer_line_id' | 'offer_line_index'>,
): QuoteLineInput[] {
  return rows.filter((row) => !isPristineLineRow(row)).map((row, index) => ({
    ...(row.id ? { id: row.id } : {}),
    product_id: row.product_id as number,
    quantity: row.quantity as number,
    unit_price: row.unit_price as number,
    vat_rate_id: row.vat_rate_id,
    ...(row.additional_description !== undefined
      ? { additional_description: row.additional_description?.trim() || null }
      : {}),
    sort_order: index,
    ...(resolveOfferLineReference ? resolveOfferLineReference(row) : {}),
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
 * Resolves a COST row's client-only `offer_line_key` into the wire's
 * `offer_line_id` (the referenced OFFER row already has a persisted id) or
 * `offer_line_index` (a brand-new row, positioned among the OFFER rows this
 * SAME request actually sends — pristine rows are dropped first, exactly
 * like `toLineInputs` itself drops them, so the two indices never drift
 * apart). A stale key (its target removed from the form) or the absence of
 * one both resolve to a generic cost: no key travels (spec 0144 D-4/D-7).
 */
export function offerLineReferenceResolver(
  offerLines: QuoteLineFormValues[],
): (row: QuoteLineFormValues) => Pick<QuoteLineInput, 'offer_line_id' | 'offer_line_index'> {
  const byClientKey = new Map<string, { id?: number; index: number }>()
  offerLines
    .filter((row) => !isPristineLineRow(row))
    .forEach((row, index) => {
      if (row.client_key) {
        byClientKey.set(row.client_key, { id: row.id, index })
      }
    })

  return (row) => {
    if (!row.offer_line_key) {
      return {}
    }
    const reference = byClientKey.get(row.offer_line_key)
    if (!reference) {
      return {}
    }
    return reference.id !== undefined ? { offer_line_id: reference.id } : { offer_line_index: reference.index }
  }
}

/**
 * Spec 0144 D-7/AC-012: a COST row's `offer_line_key` may point at a product
 * row the user has since removed from the Offer tab. Rather than reaching
 * into the RHF field to rewrite it proactively (an effect racing the row
 * edit itself), the Cost tab renders this SANITIZED view: a stale key reads
 * as "Nessuno" the moment its target disappears, and any further edit on
 * that row persists the correction for real (`setField` spreads from this
 * very array). The submit-time resolver above applies the same fallback
 * independently, so the payload is correct even without this pass.
 */
export function sanitizeCostOfferLineKeys(
  rows: QuoteLineFormValues[],
  validOfferLineKeys: ReadonlySet<string>,
): QuoteLineFormValues[] {
  return rows.map((row) =>
    row.offer_line_key && !validOfferLineKeys.has(row.offer_line_key) ? { ...row, offer_line_key: null } : row,
  )
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
      ...(withCommissions ? { additional_description: line.additional_description ?? null } : {}),
      sort_order: index,
      // Spec 0144 D-2/D-4: `null`/`undefined` on a REVENUE row and on a
      // generic COST row alike — omitted here exactly like `toLineInputs`
      // omits it for the same cases, so `sameLines` below compares like-for-like.
      ...(line.offer_line_id !== null && line.offer_line_id !== undefined
        ? { offer_line_id: line.offer_line_id }
        : {}),
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
      (line.vat_rate_id ?? null) === (other.vat_rate_id ?? null) &&
      (line.additional_description ?? null) === (other.additional_description ?? null) &&
      // Spec 0144: the association travels as EITHER key, never both — a
      // change from one to the other (e.g. a generic cost newly attributed
      // via `offer_line_index`) must still be caught as a real diff.
      (line.offer_line_id ?? null) === (other.offer_line_id ?? null) &&
      (line.offer_line_index ?? null) === (other.offer_line_index ?? null)
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

/**
 * The product -> typology mapping the persisted rows already carry (spec
 * 0099, D-5), seeding the cache the live per-typology summary reads: the
 * `products/for-select` picker only exposes a typology for a product the user
 * picks IN this session, so an edit-mode form would otherwise start with
 * every pre-existing row unbucketed.
 */
export function productTypologyIdsFromLines(lines: QuoteLine[]): Record<number, number> {
  const entries: Record<number, number> = {}
  for (const line of lines) {
    if (line.product?.product_typology) {
      entries[line.product_id] = line.product.product_typology.id
    }
  }
  return entries
}

/**
 * The product -> name mapping the persisted OFFER rows already carry (spec
 * 0144), seeding the cache the Cost tab's "Associated product" column and the
 * live per-product margin block read: a row's own form value carries only
 * `product_id` (never a name), so a freshly picked OFFER product needs
 * somewhere to remember its label too (`use-quote-lines-field.ts`'s
 * `rememberProductName`).
 */
export function productNamesFromLines(lines: QuoteLine[]): Record<number, string> {
  const entries: Record<number, string> = {}
  for (const line of lines) {
    entries[line.product_id] = line.product.name
  }
  return entries
}
