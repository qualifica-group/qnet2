/**
 * Attributes CRUD types. The generic table types (columns/filters/actions/
 * rows) live in `features/table/types.ts`; this file holds only what is
 * genuinely attributes-specific — the resource, its options and its
 * create/update payloads. Source of truth: spec 0017 frozen `data_contract`,
 * aligned to the custom fields `FieldTypeRegistry` (spec 0021): an attribute
 * is a field DEFINITION with no value storage of its own (the EAV table was
 * removed), sharing its `type`/`config`/`relation_target` shape with
 * `CustomFieldDefinitionDetail`.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldConfig, CustomFieldRelationTarget, CustomFieldType, CustomFieldValue } from '@/features/custom-fields/types'

/** A single ENUM option, as persisted server-side (aligned 1:1 to `CustomFieldOptionDetail`). */
export interface AttributeOption {
  id: number
  value: string
  label: string
  color: string | null
  icon: string | null
  sort_order: number
  is_default: boolean
}

/**
 * Single attribute detail returned by GET/POST/PATCH /attributes (envelope
 * `data`). Matches `AttributeResource`.
 */
export interface AttributeDetail {
  id: number
  code: string
  name: string
  type: CustomFieldType
  description: string | null
  help_text: string | null
  placeholder: string | null
  icon: string | null
  config: CustomFieldConfig | null
  relation_target: CustomFieldRelationTarget | null
  options: AttributeOption[]
  created_at: string
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * An `AttributeDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /attributes/{id}` (`show`). Used
 * to seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface AttributeDetailWithPermissions extends AttributeDetail {
  permissions: ResourcePermissions
}

/** A single ENUM option as sent to the backend (no `id`: full-replace). */
export interface AttributeOptionInput {
  value: string
  label: string
  color?: string | null
  icon?: string | null
  sort_order?: number
  is_default?: boolean
}

/**
 * Payload for POST /attributes (create). `options` is required/non-empty only
 * when `type` is `enum`, ignored otherwise; `relation_target` only when `type`
 * is `relation` (spec AC-003/AC-018).
 */
export interface CreateAttributePayload {
  code: string
  name: string
  type: CustomFieldType
  description?: string | null
  help_text?: string | null
  placeholder?: string | null
  icon?: string | null
  config?: CustomFieldConfig | null
  relation_target?: CustomFieldRelationTarget | null
  options?: AttributeOptionInput[]
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /attributes/{id} (partial update). `options`, when sent,
 * is a full-replace of the attribute's option set.
 */
export type UpdateAttributePayload = Partial<CreateAttributePayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `AttributeForm` component (mirrors `ReferentTypeFormMode`). Duplicate (row
 * action "duplicate") is a create pre-filled from `source`.
 */
export type AttributeFormMode =
  | { type: 'create' }
  | { type: 'edit'; attribute: AttributeDetailWithPermissions }
  | { type: 'duplicate'; source: AttributeDetailWithPermissions }
