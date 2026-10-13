/**
 * Roles CRUD types. The generic table types (columns/filters/actions/rows) live
 * in `features/table/types.ts`; this file holds only what is genuinely
 * roles-specific — the role resource and its create/update payloads.
 * Source of truth: the Roles CRUD API contract (mirrors UserResource shape).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * A single per-role field-permission override row (spec 0006): a DB-driven
 * RESTRICTION within the 0004 code ceiling for one `(resource, field)` pair.
 * Absence of a row for a field means "no restriction" (full ceiling).
 */
export interface RoleFieldPermission {
  resource: string
  field: string
  visible: boolean
  editable: boolean
  required: boolean
}

/**
 * Single role detail returned by GET/POST/PATCH /roles (envelope `data`).
 * Matches RoleResource: the role plus the flat list of its permission names.
 */
export interface RoleDetail {
  id: number
  /** Legacy id; sent only to an actor allowed to see it (`legacy-ids.view`). */
  old_id?: number | null
  name: string
  permissions: string[]
  created_at: string | null
  /**
   * Ids of the users currently assigned to this role, emitted by `RoleResource`
   * (`[]` when the role has no members). Drives edit-mode hydration so the form
   * pre-selects current members. Typed optionally to stay defensive against
   * older responses.
   */
  users?: number[]
  /** This role's field-permission matrix overrides (spec 0006). */
  field_permissions: RoleFieldPermission[]
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `RoleDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /roles/{role}` (`show`). Used to
 * seed the edit form's `ResourcePermissionsProvider` without a second request.
 *
 * Named `authorization`, not `permissions`: `RoleDetail.permissions` is
 * already the role's own granted-permission names (`string[]`) — the wire
 * envelope keeps the two as top-level siblings (`data.permissions` vs the
 * envelope's `permissions`), so the flattened client type must not collide.
 */
export interface RoleDetailWithPermissions extends RoleDetail {
  authorization: ResourcePermissions
}

/** Payload for POST /roles (create). */
export interface CreateRolePayload {
  name: string
  permissions?: string[]
  /** Initial member ids. Omit to leave membership untouched. */
  users?: number[]
  /** Initial field-permission matrix (spec 0006). Omit to leave it empty. */
  field_permissions?: RoleFieldPermission[]
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /roles/{id} (partial update). Every field is optional so the
 * request only carries what actually changed. `users`: omit → membership
 * untouched; `[]` → remove all members; array → set membership. Same
 * omit/empty-array semantics apply to `field_permissions` (spec 0006).
 */
export interface UpdateRolePayload {
  name?: string
  permissions?: string[]
  users?: number[]
  field_permissions?: RoleFieldPermission[]
  /** Only the custom fields that changed, keyed by raw key (spec 0021, sparse diff). */
  custom_fields?: Record<string, CustomFieldValue>
}
