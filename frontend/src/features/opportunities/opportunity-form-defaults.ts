import { emptyProductLineRow, type ProductLineRow } from '@/features/product-lines/types'
import { normalizeDecimal } from '@/features/opportunities/opportunity-form-payload'
import { DEFAULT_MANAGER_SLOTS } from '@/features/opportunities/opportunity-schema'
import type {
  OpportunityDetail,
  OpportunityFromLeadContext,
  OpportunityProductLine,
} from '@/features/opportunities/types'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import { managerSlotsFromRefs, padManagerSlots } from '@/lib/utils'

/*
 * The opportunity form's default values: hydrated from the persisted record
 * for the in-place detail (spec 0198), or blank/lead-seeded for a create.
 */

/**
 * The create form's G.A. slots: one empty card per assignable position, G.A. 1
 * through G.A. DEFAULT_MANAGER_SLOTS (user directive 2026-07-29) — a UX
 * default, independent of the actual ceiling (spec 0080 A1's `MAX_MANAGERS`,
 * now 12): "Add slot" (`ManagerSlotsField`) reaches the rest. Empty slots are
 * gap-aware and submit as nothing, so this changes what the user SEES, not
 * what is sent.
 */
function defaultManagerSlots(): (number | null)[] {
  return Array.from({ length: DEFAULT_MANAGER_SLOTS }, () => null)
}

/**
 * Maps the hydrated `OpportunityProductLine[]` onto the form's own row shape
 * (spec 0132): `root_category_id` starts `null` — the shared `ProductLinesField`
 * (`useProductLinesField`'s `rootCategoryFor`) resolves it at render time by
 * walking the cached category tree up from `product_category_id`, so there is
 * nothing to precompute here (AC-017).
 */
function toProductLineRows(lines: OpportunityProductLine[]): ProductLineRow[] {
  return lines.map((line) => ({
    root_category_id: null,
    product_category_id: line.product_category.id,
  }))
}

/** Default values hydrated from the persisted opportunity (the in-place detail, spec 0198). */
export function editDefaults(opportunity: OpportunityDetail): OpportunityFormValues {
  return {
    name: opportunity.name,
    registry_id: opportunity.registry_id,
    referent_id: opportunity.referent_id,
    commercial_id: opportunity.commercial_id,
    reporter_id: opportunity.reporter_id,
    supervisor_id: opportunity.supervisor_id,
    source_id: opportunity.source_id,
    operational_site_id: opportunity.operational_site_id ?? null,
    product_lines: toProductLineRows(opportunity.product_lines),
    products_of_interest: (opportunity.products_of_interest ?? []).map((product) => product.id),
    rewards: (opportunity.rewards ?? []).map((reward) => ({ reward_type_id: reward.reward_type.id })),
    manager_slots: managerSlotsFromRefs(opportunity.managers),
    start_date: opportunity.start_date,
    expected_close_date: opportunity.expected_close_date,
    estimated_value: normalizeDecimal(opportunity.estimated_value),
    // A-6: the slider always holds a value; a null stored probability
    // hydrates as 0 ("0%" ≡ "not set").
    success_probability: opportunity.success_probability ?? 0,
    general_notes: opportunity.general_notes ?? null,
  }
}

/**
 * Default values of a brand-new opportunity, seeded from the lead it converts
 * when there is one, or opened on `registryId` (spec 0199, the anagrafica
 * detail's tab: its roles are handed down by `useOpportunityRegistryPreset`).
 */
export function createDefaults(
  fromLead: OpportunityFromLeadContext | undefined,
  registryId?: number,
): OpportunityFormValues {
  const empty: OpportunityFormValues = {
    // Spec 0171, D-5: no quote exists yet, so there is no automatic title to
    // prefill — blank lets the server derive it.
    name: '',
    registry_id: registryId ?? null,
    referent_id: null,
    commercial_id: null,
    reporter_id: null,
    supervisor_id: null,
    source_id: null,
    operational_site_id: null,
    // User directive 2026-07-29: the create form opens on ONE empty
    // product-line row (at least one is mandatory anyway) and on the four
    // G.A. slots, so the ranking is visible without pressing "Add" first.
    product_lines: [emptyProductLineRow()],
    products_of_interest: [],
    rewards: [],
    manager_slots: defaultManagerSlots(),
    start_date: null,
    expected_close_date: null,
    estimated_value: null,
    success_probability: 0,
    general_notes: null,
  }
  if (!fromLead) {
    return empty
  }
  // Spec 0040 MT-6: BR-1's derived fields (whichever aren't null) seed the
  // create form, whether locked (BR-2) or left free by a null derivation.
  // Amendment rev.3 (AC-102/103): the lead's 0/1 product line seeds
  // `product_lines` instead of a locked business_function_id/
  // product_category_id pair — editable/removable like any other row.
  return {
    ...empty,
    ...fromLead.values,
    // Directive 2026-07-22: the lead's Operator seeds the SECOND "Gestore
    // Account" slot, G.A. 1 coming in empty (editable/removable both), and
    // the Supervisor stays empty. Padded to the four default slots
    // (directive 2026-07-29) without ever dropping a derived one.
    manager_slots: padManagerSlots(fromLead.managerSlots, DEFAULT_MANAGER_SLOTS),
    // A lead with no product line still opens on one empty row, like the
    // standalone create form.
    product_lines:
      fromLead.productLines.length > 0 ? toProductLineRows(fromLead.productLines) : [emptyProductLineRow()],
  }
}
