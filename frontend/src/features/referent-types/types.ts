/**
 * Referent Types CRUD types. The generic table types (columns/filters/actions/
 * rows) live in `features/table/types.ts`; this file holds only what is
 * genuinely referent-types-specific — the resource and its create/update
 * payloads. Source of truth: spec 0016 frozen `data_contract` (module A).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * Single referent-type detail returned by GET/POST/PATCH
 * /referent-types (envelope `data`). Matches `ReferentTypeResource`.
 */
export interface ReferentTypeDetail {
  id: number
  /** Legacy id; sent only to an actor allowed to see it (`legacy-ids.view`). */
  old_id?: number | null
  name: string
  created_at: string
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `ReferentTypeDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /referent-types/{id}` (`show`).
 * Used to seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface ReferentTypeDetailWithPermissions extends ReferentTypeDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /referent-types (create). */
export interface CreateReferentTypePayload {
  name: string
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /referent-types/{id} (partial update). Every field is
 * optional so the request only carries what actually changed.
 */
export type UpdateReferentTypePayload = Partial<CreateReferentTypePayload>

/**
 * Discriminated form mode shared by the form hook and the
 * `ReferentTypeForm` component (mirrors `BusinessFunctionFormMode`).
 */
export type ReferentTypeFormMode =
  | { type: 'create' }
  | { type: 'edit'; referentType: ReferentTypeDetailWithPermissions }
