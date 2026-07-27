import { isEqualCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { AddressDraft, ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type {
  RequestClientAddressPayload,
  RequestClientContactPayload,
  RequestClientIdentityPayload,
  RequestWorkPanel,
  UpdateRequestWorkPayload,
} from '@/features/request-management/types'

/**
 * True when at least one applicable attribute's current value differs from
 * the panel's loaded one. `attribute_values` is a merge/replace-whole-map
 * field server-side (spec 0049 data_contract): there is no per-code sparse
 * diff to compute, only whether the map as a whole needs resending.
 */
function attributeValuesChanged(
  current: Record<string, CustomFieldValue>,
  original: Record<string, unknown>,
  codes: string[],
): boolean {
  return codes.some(
    (code) => !isEqualCustomFieldValue(current[code] ?? null, (original[code] as CustomFieldValue) ?? null),
  )
}

/**
 * The mappers below take the STRUCTURAL minimum both sides share — the
 * buffered draft (`ContactDraft`/`AddressDraft`) and the panel's loaded
 * projection (`RequestContact`/`Address`) — so current and original map
 * through the exact same function and stay comparable.
 */
type ClientContactSource = Pick<ContactDraft, 'type' | 'value' | 'label' | 'is_primary'> & { id?: number }

type ClientAddressSource = Pick<
  AddressDraft,
  'line1' | 'line2' | 'postal_code' | 'city_id' | 'province_id' | 'state_id' | 'country_id'
> & { id?: number }

/**
 * The card identity, in both directions: the buffered draft and the panel's
 * loaded projection share these keys exactly, so current and original map
 * through this same function and stay comparable.
 */
type ClientIdentitySource = Omit<PersonalDataDraft, 'id' | 'contacts' | 'addresses'>

/** The wire shape of the client's identity (full replace of the card fields). */
function toClientIdentityPayload(source: ClientIdentitySource): RequestClientIdentityPayload {
  return {
    type: source.type,
    first_name: source.first_name,
    last_name: source.last_name,
    company_name: source.company_name,
    tax_code: source.tax_code,
    vat_number: source.vat_number,
    sdi_code: source.sdi_code,
    birth_date: source.birth_date,
    birth_city_id: source.birth_city_id,
    // Same normalization the draft applies (individual defaults to male, a
    // company carries none), so a legacy null on the loaded card does not read
    // as an edit on both sides of the comparison.
    gender: source.type === 'company' ? null : (source.gender ?? 'male'),
  }
}

/** The wire row of one buffered client contact (`id` present = update). */
function toClientContactPayload(draft: ClientContactSource): RequestClientContactPayload {
  return {
    ...(draft.id !== undefined ? { id: draft.id } : {}),
    type: draft.type,
    value: draft.value,
    label: draft.label,
    is_primary: draft.is_primary,
  }
}

/** The wire row of the single buffered client address (`id` present = update). */
function toClientAddressPayload(draft: ClientAddressSource): RequestClientAddressPayload {
  return {
    ...(draft.id !== undefined ? { id: draft.id } : {}),
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
 * True when the buffered client block differs from what the panel loaded.
 * Compared on the WIRE shape, so a purely client-side difference (the `_key`
 * of a re-seeded draft) never counts as a change.
 */
function clientBlockChanged(current: unknown, original: unknown): boolean {
  return JSON.stringify(current) !== JSON.stringify(original)
}

/**
 * Builds the sparse PATCH payload (AC-062): only `opportunity_workflow_status_id`
 * and/or `attribute_values` are included, each only when it actually changed
 * from the loaded `panel`. `note` rides along ONLY when the working status
 * both changed and its target is flagged `requires_note` (spec 0054 D-5) —
 * the server rejects the note on every other write, so it is never sent
 * speculatively.
 */
export function buildRequestWorkPayload(
  values: RequestWorkFormValues,
  panel: RequestWorkPanel,
): UpdateRequestWorkPayload {
  const payload: UpdateRequestWorkPayload = {}

  const originalWorkflowStatusId = panel.workflow_status?.id ?? null
  if (values.opportunity_workflow_status_id !== originalWorkflowStatusId) {
    payload.opportunity_workflow_status_id = values.opportunity_workflow_status_id

    const targetStatus = panel.workflow_statuses.find(
      (status) => status.id === values.opportunity_workflow_status_id,
    )
    const note = values.note.trim()
    if (targetStatus?.requires_note && note !== '') {
      payload.note = note
    }
  }

  if (values.next_callback_at !== (panel.next_callback_at ?? null)) {
    payload.next_callback_at = values.next_callback_at
  }

  const codes = panel.applicable_attributes.map((attribute) => attribute.code)
  if (attributeValuesChanged(values.attribute_values, panel.attribute_values, codes)) {
    payload.attribute_values = values.attribute_values
  }

  // Sent only when the client actually has a card: without one there is no
  // identity to replace and the server has nothing to resolve the write on.
  const identity = values.client_identity
  if (identity && panel.client_identity) {
    const wire = toClientIdentityPayload(identity)
    if (clientBlockChanged(wire, toClientIdentityPayload(panel.client_identity))) {
      payload.client_identity = wire
    }
  }

  const contacts = values.client_contacts.map(toClientContactPayload)
  if (clientBlockChanged(contacts, panel.client_contacts.items.map(toClientContactPayload))) {
    payload.client_contacts = contacts
  }

  // "Prodotti di interesse" (user directive 2026-07-22): an authoritative
  // replace, so it is sent only when the SET actually changed — order is not
  // part of its identity.
  const currentProducts = [...values.products_of_interest].sort((a, b) => a - b)
  const originalProducts = panel.products_of_interest.map((product) => product.id).sort((a, b) => a - b)
  if (clientBlockChanged(currentProducts, originalProducts)) {
    payload.products_of_interest = values.products_of_interest
  }

  // Spec 0059 D-3: same authoritative-replace idiom as products of interest,
  // diffed as an unordered SET of reward-type ids.
  const currentRewardTypeIds = values.rewards.map((reward) => reward.reward_type_id).sort((a, b) => a - b)
  const originalRewardTypeIds = (panel.rewards ?? []).map((reward) => reward.reward_type.id).sort((a, b) => a - b)
  if (clientBlockChanged(currentRewardTypeIds, originalRewardTypeIds)) {
    payload.rewards = values.rewards
  }

  // Attribution (user directive 2026-07-22): each id is sent on its own, only
  // when it changed — the endpoint is sparse per key, so an untouched picker
  // never reaches the server.
  if (values.source_id !== panel.source_id) {
    payload.source_id = values.source_id
  }

  if (values.reporter_id !== panel.reporter_id) {
    payload.reporter_id = values.reporter_id
  }

  if (values.operator_id !== panel.operator_id) {
    payload.operator_id = values.operator_id
  }

  if (values.operational_site_id !== panel.operational_site_id) {
    payload.operational_site_id = values.operational_site_id
  }

  // Sent only when a row exists: clearing every field of the inline address
  // leaves the persisted one untouched — this panel has no delete affordance
  // for it, and the write path never deletes an address.
  const address = values.client_address[0]
  if (address) {
    const wire = toClientAddressPayload(address)
    const original = panel.client_address ? toClientAddressPayload(panel.client_address) : null
    if (clientBlockChanged(wire, original)) {
      payload.client_address = wire
    }
  }

  return payload
}
