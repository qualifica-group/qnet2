import type { AddressDraft, ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'
import type { ProductLineRow } from '@/features/product-lines/product-lines-field'
import type {
  CreateRequestPayload,
  RequestProductLinePayload,
  RequestClientAddressPayload,
  RequestClientContactPayload,
  RequestClientIdentityPayload,
  RequestRewardInput,
} from '@/features/request-management/types'

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

export interface BuildRequestCreatePayloadArgs {
  registryId: number | null
  identity: PersonalDataDraft
  contacts: ContactDraft[]
  address: AddressDraft | null
  productLines: ProductLineRow[]
  /** "Prodotti di interesse" (user directive 2026-07-31): optional at creation, sent only when at least one is picked. */
  productsOfInterest: number[]
  sourceId: number | null
  reporterId: number | null
  /** GA2 "Operatore": `null` whenever the actor may not assign one (the field is not rendered). */
  operatorId: number | null
  /** Sede operativa (spec 0056): the field scoping the operator list, `null` when none was picked. */
  operationalSiteId: number | null
  rewards: RequestRewardInput[]
  /**
   * The operative fields the work panel edits (user directive 2026-07-31).
   * Each is sent only when it carries something: on create there is no
   * persisted value a null/empty could clear, so the key would carry no
   * information (the same rule `operator_id` and `rewards` already follow).
   */
  nextCallbackAt: string | null
  generalNotes: string
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
  productsOfInterest,
  sourceId,
  reporterId,
  operatorId,
  operationalSiteId,
  rewards,
  nextCallbackAt,
  generalNotes,
}: BuildRequestCreatePayloadArgs): CreateRequestPayload {
  const product_lines = toProductLinesPayload(productLines)

  // Initial attribution rides along with EITHER anagrafica branch (it is
  // independent of the D-2 XOR): the Fonte/Segnalatore slots are always sent
  // (null clears them), and `rewards` only when at least one is picked — an
  // empty array would be a no-op the server need not process. `operator_id`
  // travels ONLY when set: an actor without `request-management.assignOperator`
  // never renders the field, and sending an explicit null would be a key they
  // are not entitled to submit at all. `operational_site_id` follows the same
  // "only when set" rule for a simpler reason: on create there is no persisted
  // value a null could clear, so the key would carry no information.
  const attribution = {
    source_id: sourceId,
    reporter_id: reporterId,
    ...(operatorId !== null ? { operator_id: operatorId } : {}),
    ...(operationalSiteId !== null ? { operational_site_id: operationalSiteId } : {}),
    ...(rewards.length > 0 ? { rewards } : {}),
  }

  // "Prodotti di interesse" (user directive 2026-07-31): same "only when
  // picked" rule as `rewards` — the collection is optional at creation, and an
  // empty array is a no-op the server need not process.
  const classification = {
    product_lines,
    ...(productsOfInterest.length > 0 ? { products_of_interest: productsOfInterest } : {}),
  }

  // The operative block (user directive 2026-07-31): sent only when it
  // carries something — on create there is no persisted value a null/empty
  // could clear.
  const operative = {
    ...(nextCallbackAt !== null ? { next_callback_at: nextCallbackAt } : {}),
    ...(generalNotes.trim() !== '' ? { general_notes: generalNotes.trim() } : {}),
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
