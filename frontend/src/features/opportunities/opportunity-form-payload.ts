import { sameIdSet } from '@/lib/utils'
import type {
  CreateOpportunityPayload,
  OpportunityDetail,
  OpportunityManagerRef,
  OpportunityProductLineInput,
  UpdateOpportunityPayload,
} from '@/features/opportunities/types'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/** Create-from-lead context needed by the payload builder (spec 0040 MT-6): only what governs what NOT to send. */
export interface CreatePayloadFromLead {
  leadId: number
  /** BR-1/BR-2: keys whose value is server-derived — sending them at all is `prohibited` (422), even if it matches. */
  lockedFields: readonly string[]
}

/**
 * Builds the create payload. `registry_id`/`supervisor_id` are validated
 * non-null by the create schema before submit. `registry_id` is sent as-is
 * UNLESS locked (`fromLead`, BR-1) — either way the RHF value is always
 * present at submit time, just not always sent. `product_lines` (amendment
 * rev.3) is NEVER locked — even the row derived from a linked Lead is a
 * normal, editable/removable row (AC-102/103) — so it is always sent as-is,
 * in full (the server replaces the entire collection, AC-099). When creating
 * from a Lead (spec 0040 MT-6/A-1), every field named in
 * `fromLead.lockedFields` is OMITTED entirely (not merely repeated) and
 * `lead_id` is appended. Spec 0057 (D-5): `name` is never sent — it is
 * derived server-side as `OPP_{id}`.
 */
export function buildCreatePayload(
  values: OpportunityFormValues,
  fromLead?: CreatePayloadFromLead,
): CreateOpportunityPayload {
  const locked = new Set(fromLead?.lockedFields ?? [])

  const payload: CreateOpportunityPayload = {
    commercial_id: values.commercial_id,
    reporter_id: values.reporter_id,
    // The create schema guarantees this value before the payload builder runs;
    // the shared RHF value remains nullable because edit permits clearing it.
    supervisor_id: values.supervisor_id as number,
    // Spec 0056: facoltativa, never lead-derived — always sent as-is, like `state_id` below.
    operational_site_id: values.operational_site_id,
    // Spec 0047 (D1): never BR-2-locked (an opportunity's Regione stays
    // editable even when it originates from a lead) — always sent as-is,
    // unlike the `locked.has(...)`-gated fields below.
    state_id: values.state_id,
    product_lines: completeProductLines(values.product_lines),
    products_of_interest: values.products_of_interest,
    manager_slots: values.manager_slots,
    start_date: values.start_date,
    expected_close_date: values.expected_close_date,
    estimated_value: values.estimated_value,
    success_probability: values.success_probability,
    // "Note generali" (user directive 2026-07-27): prefilled from the lead
    // but never locked — always sent as-is, like `state_id` above.
    general_notes: values.general_notes,
  }

  if (!locked.has('registry_id')) {
    payload.registry_id = values.registry_id as number
  }
  if (!locked.has('referent_id')) {
    payload.referent_id = values.referent_id
  }
  if (!locked.has('source_id')) {
    payload.source_id = values.source_id
  }

  // Spec 0059 D-3: nothing to sync away on a fresh create, so an empty set is
  // simply omitted (identical server-side outcome to sending `[]`); a
  // non-empty set is sent in full, mirroring `product_lines`.
  if (values.rewards.length > 0) {
    payload.rewards = values.rewards
  }

  if (fromLead) {
    payload.lead_id = fromLead.leadId
  }

  return payload
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original opportunity (sparse diff, mirrors leads/registries). `lead_id` is
 * never part of the update shape (BR-2: immutable, `prohibited` server-side).
 * A locked field (BR-2) sent unchanged is a no-op for the server, so it is
 * simply omitted here like any other untouched field — the caller
 * (`use-opportunity-form`) is responsible for never letting a locked field's
 * RHF value drift from its derived original in the first place.
 */
export function buildUpdatePayload(
  values: OpportunityFormValues,
  original: OpportunityDetail,
): UpdateOpportunityPayload {
  const payload: UpdateOpportunityPayload = {}

  if (values.registry_id !== original.registry_id) {
    payload.registry_id = values.registry_id as number
  }
  if (values.referent_id !== original.referent_id) {
    payload.referent_id = values.referent_id
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
  if (values.source_id !== original.source_id) {
    payload.source_id = values.source_id
  }
  if (values.operational_site_id !== (original.operational_site_id ?? null)) {
    payload.operational_site_id = values.operational_site_id
  }
  if (values.state_id !== (original.state_id ?? null)) {
    payload.state_id = values.state_id
  }
  // Amendment rev.3: the server replaces the entire row SET (AC-099) — diff
  // as an unordered collection of pairs, never positionally (row order in
  // the form carries no meaning).
  const originalProductLines = original.product_lines.map((line) => ({
    business_function_id: line.business_function.id,
    product_category_id: line.product_category.id,
  }))
  const currentProductLines = completeProductLines(values.product_lines)
  if (!sameProductLines(currentProductLines, originalProductLines)) {
    payload.product_lines = currentProductLines
  }
  // "Prodotti di interesse" (user directive 2026-07-22): an authoritative
  // replace server-side, so diff it as an unordered SET — the picker's order
  // carries no meaning.
  const originalProducts = (original.products_of_interest ?? []).map((product) => product.id)
  if (!sameIdSet(values.products_of_interest, originalProducts)) {
    payload.products_of_interest = values.products_of_interest
  }
  // Spec 0059 D-3: same authoritative-replace idiom, diffed as an unordered
  // SET of reward-type ids (the chip row's order carries no meaning).
  const originalRewardTypeIds = (original.rewards ?? []).map((reward) => reward.reward_type.id)
  if (!sameIdSet(values.rewards.map((reward) => reward.reward_type_id), originalRewardTypeIds)) {
    payload.rewards = values.rewards
  }
  // Manager slots are ORDER- and GAP-sensitive (a slot's G.A. position is
  // meaningful), so compare positionally, not as an unordered set.
  if (!sameSlots(values.manager_slots, managerSlotsFromRefs(original.managers))) {
    payload.manager_slots = values.manager_slots
  }
  if (values.start_date !== original.start_date) {
    payload.start_date = values.start_date
  }
  if (values.expected_close_date !== original.expected_close_date) {
    payload.expected_close_date = values.expected_close_date
  }
  if (values.estimated_value !== normalizeDecimal(original.estimated_value)) {
    payload.estimated_value = values.estimated_value
  }
  // A-6: the form always holds a number (default 0); a null original hydrates
  // as 0, so compare against `?? 0` to avoid a spurious 0-write on an untouched
  // field ("0%" ≡ "not set").
  if (values.success_probability !== (original.success_probability ?? 0)) {
    payload.success_probability = values.success_probability
  }
  if (values.general_notes !== (original.general_notes ?? null)) {
    payload.general_notes = values.general_notes
  }

  return payload
}

/**
 * Filters out any row still missing an id and casts the rest to the wire
 * shape. Defensive only: the schema's `superRefine` (spec 0040 amendment
 * rev.3) already blocks submit on an incomplete row, so this never actually
 * drops a row in practice — it exists because `OpportunityFormValues.product_lines`
 * stays nullable-per-id at the type level (each row is inline-editable).
 */
function completeProductLines(rows: OpportunityFormValues['product_lines']): OpportunityProductLineInput[] {
  return rows.filter(
    (row): row is OpportunityProductLineInput =>
      row.business_function_id !== null && row.product_category_id !== null,
  )
}

/** Order-independent key of a product-line pair, for set comparison. */
function productLineKey(line: OpportunityProductLineInput): string {
  return `${line.business_function_id}:${line.product_category_id}`
}

function sameProductLines(a: OpportunityProductLineInput[], b: OpportunityProductLineInput[]): boolean {
  if (a.length !== b.length) {
    return false
  }
  const keysA = new Set(a.map(productLineKey))
  const keysB = new Set(b.map(productLineKey))
  if (keysA.size !== keysB.size) {
    return false
  }
  for (const key of keysA) {
    if (!keysB.has(key)) {
      return false
    }
  }
  return true
}

/**
 * `OpportunityDetail.managers` (spec 0040) carries hydrated `{id, name,
 * position}` refs, not the gap-aware slot array the form edits — unlike
 * registries, there is no dedicated `manager_slots` field on the detail
 * response. Rebuilds the positional, gap-aware slot array from the sparse
 * `managers` list so it can be compared against the form's current slots.
 */
export function managerSlotsFromRefs(managers: OpportunityManagerRef[]): (number | null)[] {
  const highestPosition = managers.reduce((max, manager) => Math.max(max, manager.position), 0)
  const slots: (number | null)[] = new Array(highestPosition).fill(null)
  managers.forEach((manager) => {
    slots[manager.position - 1] = manager.id
  })
  return slots
}

/** Positional (order- and gap-sensitive) comparison of two G.A. slot arrays. */
function sameSlots(a: (number | null)[], b: (number | null)[]): boolean {
  const length = Math.max(a.length, b.length)
  for (let index = 0; index < length; index += 1) {
    if ((a[index] ?? null) !== (b[index] ?? null)) {
      return false
    }
  }
  return true
}

/** Normalizes `estimated_value` (may be a decimal string from the backend) to a comparable number. */
export function normalizeDecimal(value: string | number | null): number | null {
  if (value === null) {
    return null
  }
  return typeof value === 'number' ? value : Number(value)
}
