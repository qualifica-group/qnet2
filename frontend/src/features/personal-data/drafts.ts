/**
 * Helpers for the buffered (controlled) personal-data editing flow: stable
 * client keys, seeding drafts from a persisted card, and mapping drafts back to
 * the nested wire shape submitted inside the user create/edit request
 * (ADR 0012 / spec 0003). Keeping this mapping here (not in the components or the
 * user form) keeps each consumer thin and the contract in one place.
 */

import type {
  Address,
  AddressDraft,
  Contact,
  ContactDraft,
  OwnerRef,
  PersonalDataCard,
  PersonalDataDraft,
  PersonalDataFieldPermissionResolver,
  PersonalDataType,
  SiteType,
} from '@/features/personal-data/types'

let keyCounter = 0

/**
 * Stable alias of the polymorphic owner of contacts/addresses (the personal-data
 * card), matching the backend `contactable_types`/`addressable_types` allowlist.
 */
const CARD_OWNER_ALIAS = 'personal_data'

/**
 * The owner a manager persists a contact/address against, or `undefined` when
 * the card does not exist yet. Immediate persistence needs a card id, so a draft
 * without one (create mode, or an edited owner with no card) stays buffered and
 * is persisted with the parent user payload instead.
 */
export function cardOwnerRef(draft: PersonalDataDraft): OwnerRef | undefined {
  return draft.id !== undefined ? { type: CARD_OWNER_ALIAS, id: draft.id } : undefined
}

/**
 * A blank personal-data draft, used to keep the card always active in the user
 * form (create and edit): the section is never empty, so there is no add/remove
 * affordance. Defaults to the `individual` type.
 */
export function emptyPersonalDataDraft(
  type: PersonalDataType = 'individual',
): PersonalDataDraft {
  return {
    type,
    first_name: null,
    last_name: null,
    company_name: null,
    tax_code: null,
    vat_number: null,
    sdi_code: null,
    birth_date: null,
    birth_city_id: null,
    gender: type === 'company' ? null : 'male',
    contacts: [],
    addresses: [],
  }
}

/** A process-stable, collision-free key for a brand new draft row. */
export function nextDraftKey(): string {
  keyCounter += 1
  return `draft-${keyCounter}`
}

/* -------------------------------------------------------------------------- */
/* Seed: persisted card → draft tree (edit mode)                               */
/* -------------------------------------------------------------------------- */

export function contactToDraft(contact: Contact): ContactDraft {
  return {
    _key: `contact-${contact.id}`,
    id: contact.id,
    type: contact.type,
    value: contact.value,
    label: contact.label,
    is_primary: contact.is_primary,
  }
}

export function addressToDraft(address: Address): AddressDraft {
  return {
    _key: `address-${address.id}`,
    id: address.id,
    line1: address.line1,
    line2: address.line2,
    postal_code: address.postal_code,
    city_id: address.city_id,
    province_id: address.province_id,
    state_id: address.state_id,
    country_id: address.country_id,
    // Carry the hydrated geo names through so summary rows show the full
    // location; absent on the wire when the caller did not eager-load them.
    city: address.city,
    province: address.province,
    state: address.state,
    country: address.country,
    is_primary: address.is_primary,
    site_type: address.site_type,
  }
}

/** Maps a loaded card (with its children) to the buffered draft tree. */
export function cardToDraft(card: PersonalDataCard): PersonalDataDraft {
  return {
    id: card.id,
    type: card.type,
    first_name: card.first_name,
    last_name: card.last_name,
    company_name: card.company_name,
    tax_code: card.tax_code,
    vat_number: card.vat_number,
    sdi_code: card.sdi_code,
    birth_date: card.birth_date ? card.birth_date.slice(0, 10) : null,
    birth_city_id: card.birth_city_id,
    birth_city: card.birth_city,
    // Mirror emptyPersonalDataDraft: an individual always carries a gender
    // (default male; backfills a legacy null), a company carries none.
    gender: card.gender ?? (card.type === 'company' ? null : 'male'),
    contacts: card.contacts.map(contactToDraft),
    addresses: card.addresses.map(addressToDraft),
  }
}

/* -------------------------------------------------------------------------- */
/* Submit: draft tree → nested wire payload (create + edit)                    */
/* -------------------------------------------------------------------------- */

/** A single contact in the nested `personal_data.contacts[]` wire shape. */
export interface PersonalDataContactPayload {
  id?: number
  type: string
  value: string
  label: string | null
  is_primary: boolean
}

/** A single address in the nested `personal_data.addresses[]` wire shape. */
export interface PersonalDataAddressPayload {
  id?: number
  line1: string
  line2: string | null
  postal_code: string | null
  city_id: number | null
  province_id: number | null
  state_id: number | null
  country_id: number | null
  is_primary: boolean
  site_type: SiteType | null
}

/**
 * The nested `personal_data` object accepted by the user write endpoints. Every
 * key is optional so a gated payload (spec 0008, `omitNonEditableFields`) can
 * drop the scalar fields/sections a resolver marks non-editable; `draftToPayload`
 * itself always returns every key (full replace, unaffected by this widening).
 */
export interface PersonalDataPayload {
  type?: PersonalDataDraft['type']
  first_name?: string | null
  last_name?: string | null
  company_name?: string | null
  tax_code?: string | null
  vat_number?: string | null
  sdi_code?: string | null
  birth_date?: string | null
  birth_city_id?: number | null
  gender?: PersonalDataDraft['gender']
  contacts?: PersonalDataContactPayload[]
  addresses?: PersonalDataAddressPayload[]
}

function contactToPayload(draft: ContactDraft): PersonalDataContactPayload {
  return {
    ...(draft.id !== undefined ? { id: draft.id } : {}),
    type: draft.type,
    value: draft.value,
    label: draft.label,
    is_primary: draft.is_primary,
  }
}

function addressToPayload(draft: AddressDraft): PersonalDataAddressPayload {
  return {
    ...(draft.id !== undefined ? { id: draft.id } : {}),
    line1: draft.line1,
    line2: draft.line2,
    postal_code: draft.postal_code,
    city_id: draft.city_id,
    province_id: draft.province_id,
    state_id: draft.state_id,
    country_id: draft.country_id,
    is_primary: draft.is_primary,
    site_type: draft.site_type,
  }
}

/**
 * Maps the buffered draft tree to the nested `personal_data` wire payload. The
 * `contacts`/`addresses` arrays are always included (authoritative sync: an empty
 * array deletes all owned children).
 */
export function draftToPayload(draft: PersonalDataDraft): PersonalDataPayload {
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
    gender: draft.gender,
    contacts: draft.contacts.map(contactToPayload),
    addresses: draft.addresses.map(addressToPayload),
  }
}

/** The mapped payload's scalar keys, each gated by its own `personal_data.*` key. */
const SCALAR_PAYLOAD_KEYS = [
  'type',
  'first_name',
  'last_name',
  'company_name',
  'tax_code',
  'vat_number',
  'sdi_code',
  'birth_date',
  'birth_city_id',
  'gender',
] as const

/**
 * Strips the scalar fields and child sections a resolver marks non-editable
 * from a mapped `personal_data` payload (spec 0008, defense in depth: the
 * backend enforces the same rule with a CHANGE-based guard). Without a
 * resolver, the payload is returned unchanged — the ungated behaviour required
 * for the self-service profile form (AC-013).
 */
export function omitNonEditableFields(
  payload: PersonalDataPayload,
  fieldPermission?: PersonalDataFieldPermissionResolver,
): PersonalDataPayload {
  if (!fieldPermission) {
    return payload
  }

  const gated: PersonalDataPayload = { ...payload }
  for (const key of SCALAR_PAYLOAD_KEYS) {
    if (!fieldPermission(`personal_data.${key}`).editable) {
      delete gated[key]
    }
  }
  if (!fieldPermission('personal_data.contacts').editable) {
    delete gated.contacts
  }
  if (!fieldPermission('personal_data.addresses').editable) {
    delete gated.addresses
  }
  return gated
}
