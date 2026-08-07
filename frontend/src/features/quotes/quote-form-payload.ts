import { isEqualCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import { seedAttributeValues } from '@/features/attributes/attribute-values'
import { originalLineInputs, sameLines, toLineInputs } from '@/features/quotes/quote-line-values'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type {
  CreateQuotePayload,
  QuoteDetail,
  UpdateQuotePayload,
} from '@/features/quotes/types'

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
    quote_workflow_status_id: values.quote_workflow_status_id,
    // Spec 0084: la mappa viaggia sempre alla create — non c'e' nulla di
    // persistito da conservare, quindi il merge sparso lato server non serve.
    attribute_values: values.attribute_values,
    commercial_id: values.commercial_id,
    reporter_id: values.reporter_id,
    supervisor_id: values.supervisor_id,
    company_id: values.company_id,
    company_site_id: values.company_site_id,
    operational_site_id: values.operational_site_id,
    layout_id: values.layout_id,
    payment_method_id: values.payment_method_id,
    internal_notes: values.internal_notes,
    offer_lines: toLineInputs(values.offer_lines),
    cost_lines: toLineInputs(values.cost_lines),
  }
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
  // Spec 0084: la mappa viaggia solo se un valore e' cambiato. Il server fa un
  // merge SPARSO, quindi mandarla identica sarebbe un no-op costoso; mandarla
  // parziale, invece, non cancella nulla (i `code` assenti restano). Il
  // confronto e' per `code` contro l'originale SEMINATO come lo ha idratato il
  // form (`useQuoteForm`), altrimenti un booleano mai salvato leggerebbe come
  // cambiato a ogni salvataggio estraneo. I `code` sono quelli VIVI (le righe
  // offerta possono aver cambiato categoria in questo stesso form), non quelli
  // persistiti: un attributo appena diventato applicabile deve poter viaggiare.
  const originalAttributeValues = seedAttributeValues(
    original.applicable_attributes ?? [],
    original.attribute_values ?? {},
  )
  const attributeValuesChanged = Object.keys(values.attribute_values).some(
    (code) =>
      !isEqualCustomFieldValue(
        values.attribute_values[code] ?? null,
        originalAttributeValues[code] ?? null,
      ),
  )

  if (attributeValuesChanged) {
    payload.attribute_values = values.attribute_values
  }

  // Spec 0083: the transition note rides along ONLY when the status actually
  // changes — the server demands it on the transition, not on the row (AC-026),
  // so a quote already parked on a `requires_note` row saves without one.
  if (values.quote_workflow_status_id !== original.quote_workflow_status_id) {
    payload.quote_workflow_status_id = values.quote_workflow_status_id

    const note = (values.note ?? '').trim()

    if (note !== '') {
      payload.note = note
    }
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
  if (values.company_id !== original.company_id) {
    payload.company_id = values.company_id
  }
  if (values.company_site_id !== original.company_site_id) {
    payload.company_site_id = values.company_site_id
  }
  if (values.operational_site_id !== original.operational_site_id) {
    payload.operational_site_id = values.operational_site_id
  }
  if (values.layout_id !== original.layout_id) {
    payload.layout_id = values.layout_id
  }
  if (values.payment_method_id !== original.payment_method_id) {
    payload.payment_method_id = values.payment_method_id
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
