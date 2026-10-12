/**
 * Sources CRUD types. The generic table types (columns/filters/actions/
 * rows) live in `features/table/types.ts`; this file holds only what is
 * genuinely sources-specific — the resource and its create/update payloads.
 * Source of truth: spec 0018 frozen contract (mirrors ReferentTypes, spec 0016).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * Single source detail returned by GET/POST/PATCH /sources (envelope
 * `data`). Matches `SourceResource`.
 */
export interface SourceDetail {
  id: number
  /** Legacy id; sent only to an actor allowed to see it (`legacy-ids.view`). */
  old_id?: number | null
  name: string
  created_at: string
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `SourceDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /sources/{id}` (`show`). Used to
 * seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface SourceDetailWithPermissions extends SourceDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /sources (create). */
export interface CreateSourcePayload {
  name: string
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /sources/{id} (partial update). Every field is optional
 * so the request only carries what actually changed.
 */
export type UpdateSourcePayload = Partial<CreateSourcePayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `SourceForm` component (mirrors `ReferentTypeFormMode`).
 */
export type SourceFormMode =
  | { type: 'create' }
  | { type: 'edit'; source: SourceDetailWithPermissions }
