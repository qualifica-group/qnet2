import { managerSlotsFromRefs } from '@/lib/utils'
import { seedAttributeValues, toAttributeValuesMap } from '@/features/attributes/attribute-values'
import { linesToFormValues } from '@/features/quotes/quote-line-values'
import { createEmptyLineRow } from '@/features/quotes/use-quote-lines-field'
import { DEFAULT_MANAGER_SLOTS, type QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteCreateFormMode, QuoteDetail } from '@/features/quotes/types'

/*
 * The two starting points of `useQuoteForm` (extracted from the hook,
 * engineering.md §6): the persisted offer the in-place detail edits (spec
 * 0197), and the blank draft of the create form.
 */

/** Default values hydrated from the persisted quote (the in-place detail). */
export function editDefaults(quote: QuoteDetail): QuoteFormValues {
  return {
    code: quote.code,
    title: quote.title,
    opportunity_id: quote.opportunity_id,
    quote_workflow_status_id: quote.quote_workflow_status_id,
    note: null,
    // An empty PHP map serializes as a JSON ARRAY (`toAttributeValuesMap`),
    // and every applicable code needs its key for the Zod object: seeded
    // here, so the `values` re-sync after a save never hands the schema an
    // unseeded map.
    attribute_values: seedAttributeValues(
      quote.applicable_attributes,
      toAttributeValuesMap(quote.attribute_values),
    ),
    commercial_id: quote.commercial_id,
    reporter_id: quote.reporter_id,
    supervisor_id: quote.supervisor_id,
    manager_slots: managerSlotsFromRefs(quote.managers ?? []),
    company_id: quote.company_id,
    company_site_id: quote.company_site_id,
    operational_site_id: quote.operational_site_id,
    layout_id: quote.layout_id,
    payment_method_id: quote.payment_method_id,
    internal_notes: quote.internal_notes,
    rewards: (quote.rewards ?? []).map((reward) => ({ reward_type_id: reward.reward_type.id })),
    offer_lines: linesToFormValues(quote.offer_lines),
    cost_lines: linesToFormValues(quote.cost_lines),
  }
}

/**
 * The create form's G.A. slots: one empty card per assignable position, up
 * through `DEFAULT_MANAGER_SLOTS` (mirrors `useOpportunityForm`'s own
 * `defaultManagerSlots`) — a UX default, independent of `MAX_MANAGERS`. D-5's
 * actual inheritance from the Opportunity happens server-side once the form
 * submits this untouched (`buildCreatePayload` omits the key).
 */
function defaultManagerSlots(): (number | null)[] {
  return Array.from({ length: DEFAULT_MANAGER_SLOTS }, () => null)
}

/** Default values of a brand-new quote, its `code` prefilled with the suggestion (D-13/AC-082). */
export function createDefaults(mode: QuoteCreateFormMode, initialCode: string | undefined): QuoteFormValues {
  // Spec 0067 AC-050/051: a numeric `params.opportunity_id` seeds the
  // otherwise-empty create form (the panel "Crea Offerta" flow); the field is
  // then locked by the create sections' `forceDisabled`.
  const forcedOpportunityId = typeof mode.params?.opportunity_id === 'number' ? mode.params.opportunity_id : null
  return {
    code: initialCode ?? '',
    title: '',
    opportunity_id: forcedOpportunityId,
    quote_workflow_status_id: null,
    note: null,
    attribute_values: {},
    commercial_id: null,
    reporter_id: null,
    supervisor_id: null,
    // Spec 0087 (D-5): left on the UX default (empty cards); the actual
    // Opportunity inheritance happens server-side once submitted untouched
    // (`buildCreatePayload` then omits the key).
    manager_slots: defaultManagerSlots(),
    company_id: null,
    company_site_id: null,
    operational_site_id: null,
    // Precompiled with the module's active default layout by
    // `useQuoteCreateDefaults` (spec 0070 D-3/AC-310), not here: resolving it
    // needs a network round trip, out of scope for a synchronous default.
    layout_id: null,
    // No server-side default to mirror (unlike `layout_id`): a new quote
    // starts with no payment method until the user picks one.
    payment_method_id: null,
    internal_notes: null,
    // Directive 2026-08-31: a new Offerta starts with NO buono — the
    // Opportunita's own assignments are deliberately not copied over, or the
    // same segnalatore would be counted twice in "Segnalatori premiati".
    rewards: [],
    // Directive 2026-09-01: the Offerta tab opens on ONE empty row instead of
    // an empty grid — an offer without lines is the exception, so making the
    // user press "Aggiungi riga" first was pure friction. Costs stay empty:
    // those rows are genuinely optional.
    offer_lines: [createEmptyLineRow()],
    cost_lines: [],
  }
}
