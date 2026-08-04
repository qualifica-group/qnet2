/**
 * Centralized authorization metadata types (spec 0004). The backend is the
 * single source of truth: every resource + field + action flag below is
 * computed server-side (`AbstractResourceAuthorization`) and the frontend only
 * renders itself from it — never derives authorization on its own.
 */

/** Standard CRUD(+) abilities, mapped 1:1 to the `{resource}.{ability}` Policy convention. */
export type ResourceAbility =
  | 'view'
  | 'create'
  | 'update'
  | 'delete'
  | 'export'
  | 'import'

/**
 * Per-field authorization descriptor. All six flags are always emitted by the
 * backend (`FieldPermission::toArray()`); see the spec for the derivation
 * rules (`hidden = !visible`, `readonly = visible && !editable && !disabled`).
 */
export interface FieldPermission {
  visible: boolean
  hidden: boolean
  editable: boolean
  readonly: boolean
  required: boolean
  disabled: boolean
}

/** The `permissions` block attached alongside `data` in the response envelope. */
export interface ResourcePermissions {
  resource: Record<ResourceAbility, boolean>
  fields: Record<string, FieldPermission>
  actions: Record<string, boolean>
  /**
   * Field keys the actor may PROPOSE a change on, even though they cannot
   * write them directly (spec 0078): drives `useResourcePermissions().canRequestChange`.
   * Always present on `GET /meta/{resource}` and instance detail payloads
   * (never `null`/absent); an entry not listed here simply cannot be proposed.
   */
  change_requestable_fields?: string[]
}

/** A single entry of the create-context field catalogue (`GET /meta/{resource}`). */
export interface FieldDescriptor {
  key: string
  /** Form control type hint, e.g. `text`, `email`, `select`, `multiselect`, `password`. */
  type: string
  group: string | null
  /**
   * Field vital to creating the resource (spec 0008): the Role matrix locks its
   * checkboxes (visible/editable/required forced on, disabled) and the server
   * refuses any role_field_permissions row that would narrow it.
   */
  mandatory: boolean
}

/** Response shape of `GET /meta/{resource}`, already unwrapped from the envelope. */
export interface ResourceMeta {
  fields: FieldDescriptor[]
  permissions: ResourcePermissions
}
