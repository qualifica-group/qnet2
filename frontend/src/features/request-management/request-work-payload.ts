import type { Address, AddressDraft, ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'
import type { ProductLineRow } from '@/features/product-lines/types'
import { toProductLinesPayload } from '@/features/request-management/request-create-payload'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type {
  RequestClientAddressPayload,
  RequestClientContactPayload,
  RequestClientIdentity,
  RequestClientIdentityPayload,
  RequestContact,
  RequestProductLine,
  RequestWorkPanel,
  UpdateRequestWorkPayload,
} from '@/features/request-management/types'

/**
 * The panel's loaded funzione/categoria pairs in the row shape the field
 * editor (and the diff above) works with — the form defaults, the schema's
 * baseline and the payload's own comparison all go through this one mapper.
 */
export function toProductLineRows(lines: RequestProductLine[]): ProductLineRow[] {
  return lines.map((line) => ({
    business_function_id: line.business_function.id,
    product_category_id: line.product_category.id,
  }))
}

/**
 * True when the funzione/categoria rows differ from the panel's loaded ones
 * (user directive 2026-07-31). Compared as an unordered SET of pairs: a row's
 * position carries no meaning, server-side either (the pair is what is
 * unique). Exported for the same reason as the two above — the schema
 * validates the collection only when it is going to be sent.
 */
export function productLinesChanged(current: ProductLineRow[], original: ProductLineRow[]): boolean {
  const keys = (rows: ProductLineRow[]) =>
    rows.map((row) => `${row.business_function_id}:${row.product_category_id}`).sort()

  return clientBlockChanged(keys(current), keys(original))
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
    residence_city_id: source.residence_city_id,
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
 * The three predicates below decide whether a buffered client block is going
 * to be sent. Exported for the SAME reason as the two above: the schema
 * validates a block only when it travels, since an untouched one is never
 * validated server-side either (`ValidatesRequestClientProfile` only sees
 * what the PATCH carries).
 */
export function clientIdentityChanged(
  current: PersonalDataDraft | null,
  original: RequestClientIdentity | null,
): boolean {
  // No card on either side: there is no identity to replace, so nothing is sent.
  if (!current || !original) {
    return false
  }

  return clientBlockChanged(toClientIdentityPayload(current), toClientIdentityPayload(original))
}

export function clientContactsChanged(current: ContactDraft[], original: RequestContact[]): boolean {
  return clientBlockChanged(current.map(toClientContactPayload), original.map(toClientContactPayload))
}

export function clientAddressChanged(current: AddressDraft[], original: Address | null): boolean {
  const address = current[0]

  // No row buffered: clearing the inline fields leaves the persisted address
  // untouched, so nothing travels (mirrors the payload builder below).
  if (!address) {
    return false
  }

  return clientBlockChanged(
    toClientAddressPayload(address),
    original ? toClientAddressPayload(original) : null,
  )
}

/**
 * Builds the sparse PATCH payload (AC-062): each key is included only when it
 * actually changed from the loaded `panel`.
 */
export function buildRequestWorkPayload(
  values: RequestWorkFormValues,
  panel: RequestWorkPanel,
): UpdateRequestWorkPayload {
  const payload: UpdateRequestWorkPayload = {}

  if (values.next_callback_at !== (panel.next_callback_at ?? null)) {
    payload.next_callback_at = values.next_callback_at
  }

  // Sent only when the client actually has a card: without one there is no
  // identity to replace and the server has nothing to resolve the write on.
  if (values.client_identity && clientIdentityChanged(values.client_identity, panel.client_identity)) {
    payload.client_identity = toClientIdentityPayload(values.client_identity)
  }

  if (clientContactsChanged(values.client_contacts, panel.client_contacts.items)) {
    payload.client_contacts = values.client_contacts.map(toClientContactPayload)
  }

  // "Funzione aziendale" + "categoria prodotto" (user directive 2026-07-31):
  // an authoritative-replace idiom, sent only when the pairs changed. Every
  // row is complete by then — the schema refuses the submit otherwise.
  if (productLinesChanged(values.product_lines, toProductLineRows(panel.product_lines))) {
    payload.product_lines = toProductLinesPayload(values.product_lines)
  }

  // Spec 0059 D-3: same authoritative-replace idiom as the collections above,
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
  if (address && clientAddressChanged(values.client_address, panel.client_address)) {
    payload.client_address = toClientAddressPayload(address)
  }

  return payload
}
