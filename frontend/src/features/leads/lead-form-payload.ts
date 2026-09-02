import { entriesToRecord } from '@/features/leads/extra-fields'
import type { CreateLeadPayload, LeadDetail, UpdateLeadPayload } from '@/features/leads/types'
import type { LeadFormValues } from '@/features/leads/use-lead-form'

/**
 * Builds the create payload. `registry_id`/`campaign_id` are validated
 * non-null by the schema's refine before submit (BR-1, D-1). Lead status is
 * derived server-side and is not submitted. `state_id` (directive
 * 2026-07-21) is sent unconditionally, mirroring the opportunity form: the
 * free, user-editable Regione — the form's current value, never inherited
 * from the Sede.
 */
export function buildCreatePayload(values: LeadFormValues): CreateLeadPayload {
  return {
    registry_id: values.registry_id as number,
    campaign_id: values.campaign_id as number,
    operational_site_id: values.operational_site_id,
    source_id: values.source_id,
    operator_id: values.operator_id,
    state_id: values.state_id,
    notes: values.notes,
    extra_fields: entriesToRecord(values.extra_fields),
    products_of_interest: values.products_of_interest,
    convert_to_opportunity: values.convert_to_opportunity,
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original lead (spec 0024, sparse diff, mirrors campaigns). `registry_id`/
 * `campaign_id`, when changed, are always non-null (the schema forbids
 * clearing either, BR-1, D-1). Lead status is derived server-side.
 * `convert_to_opportunity` (spec 0044) is deliberately never read here:
 * conversion in edit mode is out of scope, so the update payload never
 * carries the flag.
 */
export function buildUpdatePayload(values: LeadFormValues, original: LeadDetail): UpdateLeadPayload {
  const payload: UpdateLeadPayload = {}

  if (values.registry_id !== original.registry_id) {
    payload.registry_id = values.registry_id as number
  }
  if (values.campaign_id !== original.campaign_id) {
    payload.campaign_id = values.campaign_id as number
  }
  if (values.operational_site_id !== original.operational_site_id) {
    payload.operational_site_id = values.operational_site_id
  }
  if (values.source_id !== original.source_id) {
    payload.source_id = values.source_id
  }
  if (values.operator_id !== original.operator_id) {
    payload.operator_id = values.operator_id
  }
  if (values.state_id !== (original.state_id ?? null)) {
    payload.state_id = values.state_id
  }
  if (values.notes !== original.notes) {
    payload.notes = values.notes
  }
  const nextExtraFields = entriesToRecord(values.extra_fields)
  if (!extraFieldsEqual(nextExtraFields, original.extra_fields)) {
    payload.extra_fields = nextExtraFields
  }

  const originalProductIds = (original.products_of_interest ?? []).map((product) => product.id)
  if (!productIdsEqual(values.products_of_interest, originalProductIds)) {
    payload.products_of_interest = values.products_of_interest
  }

  return payload
}

/** Order-independent equality of two `extra_fields` records, for the update sparse diff. */
function extraFieldsEqual(a: Record<string, string> | null, b: Record<string, string> | null): boolean {
  if (a === null || b === null) return a === b
  const aKeys = Object.keys(a).sort()
  const bKeys = Object.keys(b).sort()
  if (aKeys.length !== bKeys.length) return false
  return aKeys.every((key, index) => key === bKeys[index] && a[key] === b[key])
}

/** Order-independent equality of two product-id sets, for the `products_of_interest` sparse diff. */
function productIdsEqual(a: number[], b: number[]): boolean {
  if (a.length !== b.length) return false
  const sortedA = [...a].sort((x, y) => x - y)
  const sortedB = [...b].sort((x, y) => x - y)
  return sortedA.every((id, index) => id === sortedB[index])
}
