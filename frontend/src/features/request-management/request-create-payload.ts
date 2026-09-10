import { isEmptyCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { AddressDraft, ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'
import type { ProductLineRow } from '@/features/product-lines/product-lines-field'
import { toLineInputs } from '@/features/quotes/quote-line-values'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type {
  CreateRequestPayload,
  RequestProductLinePayload,
  RequestClientAddressPayload,
  RequestClientContactPayload,
  RequestClientIdentityPayload,
  RequestRewardInput,
} from '@/features/request-management/request-write-types'

/** The wire shape of the client's identity (full create, no `id`: the server always makes a new card). */
function toClientIdentityPayload(draft: PersonalDataDraft): RequestClientIdentityPayload {
  return {
    type: draft.type,
    first_name: draft.first_name,
    last_name: draft.last_name,
    company_name: draft.company_name,
    tax_code: draft.tax_code,
    vat_number: draft.vat_number,
    sdi_code: draft.sdi_code,
    birth_date: draft.birth_date,
    birth_city_id: draft.birth_city_id,
    residence_city_id: draft.residence_city_id,
    // Mirrors the card form's own normalization: an individual always carries
    // a gender (default male), a company carries none.
    gender: draft.type === 'company' ? null : (draft.gender ?? 'male'),
  }
}

/** The wire row of one buffered client contact (no `id`: every one is new). */
function toClientContactPayload(draft: ContactDraft): RequestClientContactPayload {
  return { type: draft.type, value: draft.value, label: draft.label, is_primary: draft.is_primary }
}

/** The wire row of the single buffered client address (no `id`: it is new). */
function toClientAddressPayload(draft: AddressDraft): RequestClientAddressPayload {
  return {
    line1: draft.line1,
    line2: draft.line2,
    postal_code: draft.postal_code,
    city_id: draft.city_id,
    province_id: draft.province_id,
    state_id: draft.state_id,
    country_id: draft.country_id,
  }
}

/**
 * Rows are guaranteed complete (both ids chosen) by the schema that gates the
 * submit — `buildRequestCreateSchema` here, `buildRequestWorkSchema` for the
 * work panel, which shares this mapper (user directive 2026-07-31).
 */
export function toProductLinesPayload(rows: ProductLineRow[]): RequestProductLinePayload[] {
  return rows.map((row) => ({
    business_function_id: row.business_function_id as number,
    product_category_id: row.product_category_id as number,
  }))
}

/**
 * Whether the dynamic block carries anything worth sending: at least one
 * applicable code holds a non-empty value. THE gate for both the payload
 * (which omits `attribute_values` entirely otherwise) and the schema's
 * required-codes rule — sharing it is what keeps "what travels" and "what is
 * validated" from drifting apart, exactly as on the work panel.
 */
export function attributeValuesFilled(values: Record<string, unknown>, codes: string[]): boolean {
  return codes.some((code) => !isEmptyCustomFieldValue(values[code]))
}

/**
 * The submitted map narrowed to the APPLICABLE codes: the form keeps the
 * values of a category the operator has since removed (RHF never prunes keys),
 * and the server rejects a code outside the applicable set with a 422.
 */
function pickAttributeValues(values: Record<string, unknown>, codes: string[]): Record<string, CustomFieldValue> {
  return Object.fromEntries(codes.map((code) => [code, (values[code] as CustomFieldValue) ?? null]))
}

export interface BuildRequestCreatePayloadArgs {
  registryId: number | null
  identity: PersonalDataDraft
  contacts: ContactDraft[]
  address: AddressDraft | null
  productLines: ProductLineRow[]
  /** "Linee dell'offerta" (user directive 2026-08-07): optional at creation, sent only when at least one row exists. */
  offerLines: QuoteLineFormValues[]
  sourceId: number | null
  reporterId: number | null
  /** Spec 0097 rev-2 D-9: the created Offerta's Supervisore, `null` when none was picked. */
  supervisorId: number | null
  /**
   * Spec 0097 D-1: the created Offerta's team, ordered and gap-aware. All-null
   * (or empty) whenever the actor may not assign one — the block is not
   * rendered then — which is what keeps the key off the wire below.
   */
  managerSlots: (number | null)[]
  /** Sede operativa (spec 0056): the field scoping the operator list, `null` when none was picked. */
  operationalSiteId: number | null
  rewards: RequestRewardInput[]
  /**
   * The operative fields the work panel edits (user directive 2026-07-31).
   * Each is sent only when it carries something: on create there is no
   * persisted value a null/empty could clear, so the key would carry no
   * information (the same rule `manager_slots` and `rewards` already follow).
   */
  nextCallbackAt: string | null
  generalNotes: string
  /** "Informazioni aggiuntive" (user directive 2026-08-07): the submitted dynamic map. */
  attributeValues: Record<string, unknown>
  /** The applicable codes, i.e. which keys of `attributeValues` are eligible to travel. */
  attributeCodes: string[]
}

/**
 * Builds the frozen `POST /api/request-management` payload (spec 0057, D-2):
 * exactly one anagrafica source. `registryId` set — the client identity/
 * contacts/address buffers are dropped entirely, the server rejects both
 * branches sent together; unset — they are mapped onto the flat `client_*`
 * blocks the endpoint expects.
 */
export function buildRequestCreatePayload({
  registryId,
  identity,
  contacts,
  address,
  productLines,
  offerLines,
  sourceId,
  reporterId,
  supervisorId,
  managerSlots,
  operationalSiteId,
  rewards,
  nextCallbackAt,
  generalNotes,
  attributeValues,
  attributeCodes,
}: BuildRequestCreatePayloadArgs): CreateRequestPayload {
  const product_lines = toProductLinesPayload(productLines)

  // Initial attribution rides along with EITHER anagrafica branch (it is
  // independent of the D-2 XOR): the Fonte/Segnalatore slots are always sent
  // (null clears them), and `rewards` only when at least one is picked — an
  // empty array would be a no-op the server need not process. `manager_slots`
  // travels ONLY once a slot is actually filled (spec 0097 D-1): an actor
  // without `request-management.assignOperator` never renders the block, and
  // an untouched set of empty cards carries no intent — omitted, the server
  // applies its own default (the connected actor on the operator slot), which
  // is exactly what the removed `operator_id` key used to leave it to.
  // `operational_site_id` follows the same "only when set" rule for a simpler
  // reason: on create there is no persisted value a null could clear, so the
  // key would carry no information — and `supervisor_id` (spec 0097 rev-2
  // D-9) rides with it: the create form has no `permissions` envelope to gate
  // the field against, so sending an untouched null would earn a 422 from any
  // actor who may not write it.
  const attribution = {
    source_id: sourceId,
    reporter_id: reporterId,
    ...(supervisorId !== null ? { supervisor_id: supervisorId } : {}),
    ...(managerSlots.some((slot) => slot !== null) ? { manager_slots: managerSlots } : {}),
    ...(operationalSiteId !== null ? { operational_site_id: operationalSiteId } : {}),
    ...(rewards.length > 0 ? { rewards } : {}),
  }

  // "Linee dell'offerta" (user directive 2026-08-07): the same "only when
  // filled in" rule `rewards` follows — an empty array would ask the server to
  // replace nothing with nothing on an Offerta that is being created empty
  // anyway. The gate reads the WIRE rows, not the form ones: since directive
  // 2026-09-01 the form opens on an untouched row that `toLineInputs` drops.
  const offerLineInputs = toLineInputs(offerLines)
  const classification = {
    product_lines,
    ...(offerLineInputs.length > 0 ? { offer_lines: offerLineInputs } : {}),
  }

  // The operative block (user directive 2026-07-31): sent only when it
  // carries something — on create there is no persisted value a null/empty
  // could clear.
  // The dynamic map travels as a whole or not at all — the server's
  // `is_required` check looks at SUBMITTED codes, so sending a map of empty
  // values would demand every required attribute of a request nobody has
  // worked yet.
  const operative = {
    ...(nextCallbackAt !== null ? { next_callback_at: nextCallbackAt } : {}),
    ...(generalNotes.trim() !== '' ? { general_notes: generalNotes.trim() } : {}),
    ...(attributeValuesFilled(attributeValues, attributeCodes)
      ? { attribute_values: pickAttributeValues(attributeValues, attributeCodes) }
      : {}),
  }

  if (registryId !== null) {
    return { registry_id: registryId, ...classification, ...attribution, ...operative }
  }

  return {
    client_identity: toClientIdentityPayload(identity),
    client_contacts: contacts.map(toClientContactPayload),
    ...(address ? { client_address: toClientAddressPayload(address) } : {}),
    ...classification,
    ...attribution,
    ...operative,
  }
}
