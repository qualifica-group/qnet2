/**
 * Referents CRUD types. The generic table types (columns/filters/actions/
 * rows) live in `features/table/types.ts`; this file holds only what is
 * genuinely referents-specific — the resource and its create/update payloads.
 * Source of truth: spec 0016 frozen `data_contract` (module B). The nested
 * `personal_data` sub-contract is reused unchanged from `features/personal-data`.
 */

import type { PersonalDataCard } from '@/features/personal-data/types'
import type { PersonalDataPayload } from '@/features/personal-data/drafts'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/** The contact ambit of a referent: internal to the organization, or external. */
export const REFERENT_CONTACT_SCOPES = ['internal', 'external'] as const
export type ReferentContactScope = (typeof REFERENT_CONTACT_SCOPES)[number]

/** A referent-type reference as hydrated in the referent resource ({id, name}). */
export interface ReferentTypeRef {
  id: number
  name: string
}

/** The linked user reference as hydrated in the referent resource ({id, name}, spec 0090). */
export interface ReferentUserRef {
  id: number
  name: string
}

/**
 * Single referent detail returned by GET/POST/PATCH /referents (envelope
 * `data`). Matches `ReferentResource`.
 */
export interface ReferentDetail {
  id: number
  name: string
  referent_type_id: number | null
  /** Hydrates the "Referent type" single-select control. */
  referent_type: ReferentTypeRef | null
  /**
   * The system user declared to BE this referent (spec 0090 D-2), or `null`
   * if unlinked. Both keys are OMITTED (not `null`) when `user_id` is not
   * visible for field permission.
   */
  user_id?: number | null
  /** Hydrates the "Linked user" single-select control. */
  user?: ReferentUserRef | null
  contact_scope: ReferentContactScope
  notes: string | null
  /**
   * The referent's personal-data card (with its contacts and addresses).
   * `null` only in the pathological case of a referent with no card yet.
   */
  personal_data: PersonalDataCard | null
  created_at: string
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `ReferentDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /referents/{id}` (`show`). Used to
 * seed the edit form's `ResourcePermissionsProvider` — and the anagraphic
 * draft, via `cardToDraft(personal_data)` — without a second request.
 */
export interface ReferentDetailWithPermissions extends ReferentDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /referents (create). `personal_data` is REQUIRED: the
 * referent's `name` is derived server-side from the card (ADR 0012 pattern,
 * reused here via `ReferentProfileWriter`).
 */
export interface CreateReferentPayload {
  referent_type_id?: number | null
  /** The linked user's id, or `null` for none (spec 0090 D-2). */
  user_id?: number | null
  contact_scope: ReferentContactScope
  notes?: string | null
  personal_data: PersonalDataPayload
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /referents/{id} (partial update). Every field is optional
 * so the request only carries what actually changed; `personal_data`, when
 * present, is a full-replace sync of contacts/addresses.
 */
export interface UpdateReferentPayload {
  referent_type_id?: number | null
  /** The linked user's id, `null` to unlink (spec 0090 D-2/AC-003). */
  user_id?: number | null
  contact_scope?: ReferentContactScope
  notes?: string | null
  personal_data?: PersonalDataPayload
  /** Only the custom fields that changed, keyed by raw key (spec 0021, sparse diff). */
  custom_fields?: Record<string, CustomFieldValue>
}

/** Discriminated form mode shared by the form hook/meta-resolver and `ReferentForm`. */
export type ReferentFormMode =
  | { type: 'create' }
  | { type: 'edit'; referent: ReferentDetailWithPermissions }
