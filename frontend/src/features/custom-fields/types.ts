import type { FieldDescriptor, ResourcePermissions } from '@/features/authorization/types'

/**
 * Universal custom fields (spec 0021): a module-agnostic renderer that turns
 * the enriched `source:'custom'` descriptors emitted by `GET /meta/{resource}`
 * into form controls. Nothing here is Companies-specific — any resource that
 * mounts `<CustomFieldsSection resource="..." />` gets the same engine.
 */

/** MVP custom field types (backend `FieldTypeRegistry`). */
export type CustomFieldType =
  | 'text'
  | 'textarea'
  | 'integer'
  | 'decimal'
  | 'boolean'
  | 'enum'
  | 'relation'
  | 'date'
  | 'datetime'
  | 'time'
  | 'email'
  | 'url'
  | 'color'
  | 'table'

/** Runtime list of {@link CustomFieldType}, driving the admin form's `type` picker. */
export const CUSTOM_FIELD_TYPES: readonly CustomFieldType[] = [
  'text',
  'textarea',
  'integer',
  'decimal',
  'boolean',
  'enum',
  'relation',
  'date',
  'datetime',
  'time',
  'email',
  'url',
  'color',
  'table',
]

/**
 * Merged, per-type-optional config shape (the backend stores `config` as a
 * loose JSON object, one subset of these keys populated per `type`):
 * - text: minLength/maxLength/regex/transform
 * - textarea: rows/maxLength
 * - integer/decimal: min/max/step/decimals
 * - boolean: display ('checkbox'|'switch')
 * - enum: display ('select'|'multiselect'|'radio'|'badge')
 * - date/datetime/time/email/url/color: no extra config (native HTML input)
 * - table: columns/selectable/summary/min_rows/max_rows (see {@link TableFieldConfig})
 */
export interface CustomFieldConfig {
  minLength?: number
  maxLength?: number
  regex?: string
  transform?: 'upper' | 'lower' | 'capitalize'
  rows?: number
  min?: number
  max?: number
  step?: number
  decimals?: number
  display?: 'checkbox' | 'switch' | 'select' | 'multiselect' | 'radio' | 'badge'
  columns?: TableFieldColumn[]
  selectable?: TableFieldSelectable | null
  summary?: TableFieldSummary | null
  min_rows?: number
  max_rows?: number
}

/** Column types a `table` field accepts (spec 0180 D-2: simple types only, no relation/nested table). */
export type TableColumnType = Exclude<CustomFieldType, 'relation' | 'table'>

/** One column of a `table` definition; `config`/`options` reuse the per-type shapes of a plain field. */
export interface TableFieldColumn {
  key: string
  label: string
  type: TableColumnType
  required?: boolean
  config?: CustomFieldConfig | null
  options?: CustomFieldOption[]
}

/** The "only one row" radio column of a `table` definition. */
export interface TableFieldSelectable {
  key: string
  label: string
}

export type TableSummaryStrategy = 'selected' | 'max' | 'min'

/** Which column, and how, feeds the grid summary of a `table` value. */
export interface TableFieldSummary {
  column: string
  strategy: TableSummaryStrategy
}

/** `config` of a `type:'table'` definition (spec 0180 data_contract). */
export interface TableFieldConfig {
  columns: TableFieldColumn[]
  selectable?: TableFieldSelectable | null
  summary?: TableFieldSummary | null
  min_rows?: number
  max_rows?: number
}

/** A cell value: a scalar of the column type, `null` when unset. */
export type TableFieldCell = string | number | boolean | null

/** One row: `id` assigned by the server (absent on new rows), then one entry per column key (+ the selectable key). */
export interface TableFieldRow {
  id?: string | null
  [columnKey: string]: TableFieldCell | undefined
}

/** Wire/read value of a `table` field; `summary` is server-computed and never sent. */
export interface TableFieldValue {
  rows: TableFieldRow[]
  summary?: string | number | null
}

/** A selectable value for an `enum` custom field. */
export interface CustomFieldOption {
  value: string
  label: string
  color?: string | null
  icon?: string | null
}

/** Target + arity of a `relation` custom field, resolved to a for-select resource. */
export interface CustomFieldRelation {
  for_select_resource: string
  cardinality: 'one' | 'many'
}

/**
 * The enriched `FieldDescriptor` variant emitted for `source:'custom'` fields
 * (native fields keep the minimal base shape, untouched). `key` stays
 * namespaced (`custom.<rawKey>`) — see {@link rawKey}/{@link namespacedKey}.
 */
export interface CustomFieldDescriptor extends FieldDescriptor {
  type: CustomFieldType
  /** Human label; native `FieldDescriptor` has none (labels come from FE i18n there), custom fields carry their own. */
  label: string
  source: 'custom'
  description?: string | null
  help_text?: string | null
  placeholder?: string | null
  icon?: string | null
  tab?: string | null
  sort_order?: number
  config?: CustomFieldConfig | null
  options?: CustomFieldOption[]
  relation?: CustomFieldRelation
}

/**
 * The wire value of a custom field: a scalar (text/textarea/integer/decimal),
 * a boolean, a relation id or id[] (single/multi), or an enum value or
 * value[] (single/multi), or a {@link TableFieldValue}. `null` represents "not set".
 */
export type CustomFieldValue =
  | string
  | number
  | boolean
  | string[]
  | number[]
  | TableFieldValue
  | null

/** Shape a host form must extend to mount `<CustomFieldsSection>` (§CustomFieldsSection.tsx). */
export interface CustomFieldsFormShape {
  custom_fields: Record<string, CustomFieldValue>
}

const NAMESPACE_PREFIX = 'custom.'

/** Strips the `custom.` namespace off a meta key, yielding the wire/storage key. */
export function rawKey(key: string): string {
  return key.startsWith(NAMESPACE_PREFIX) ? key.slice(NAMESPACE_PREFIX.length) : key
}

/** Re-applies the `custom.` namespace to a raw wire key, yielding the meta key. */
export function namespacedKey(raw: string): string {
  return `${NAMESPACE_PREFIX}${raw}`
}

/**
 * Narrows a generic `FieldDescriptor` (as returned by `useResourceMeta`) to
 * the enriched `CustomFieldDescriptor` shape. The base type only declares the
 * minimal native-field fields, so the enrichment is read defensively at
 * runtime rather than assumed from the (necessarily narrower) TS type.
 */
export function isCustomFieldDescriptor(field: FieldDescriptor): field is CustomFieldDescriptor {
  return (field as { source?: unknown }).source === 'custom'
}

/*
 * ---------------------------------------------------------------------------
 * ADMIN CRUD (spec 0021 — ADMIN PANEL): the `CustomFieldDefinition` catalogue
 * itself, managed at `/custom-fields`. Distinct from the descriptors above,
 * which describe a field on its HOST resource's meta, not the definition row.
 * ---------------------------------------------------------------------------
 */

/** A single selectable value of an `enum` definition, as persisted server-side. */
export interface CustomFieldOptionDetail {
  id: number
  value: string
  label: string
  color: string | null
  icon: string | null
  sort_order: number
  is_default: boolean
}

/** Validation rules stored on the definition (backend `validation` JSON column). */
export interface CustomFieldValidation {
  required?: boolean
  unique?: boolean
  min?: number
  max?: number
  between?: [number, number]
  regex?: string
  email?: boolean
  url?: boolean
  exists?: boolean
  distinct?: boolean
}

/** Full relation target as stored on the definition (admin write-path; includes `entity_type`, unlike the read-side {@link CustomFieldRelation}). */
export interface CustomFieldRelationTarget {
  entity_type: string
  cardinality: 'one' | 'many'
  for_select_resource: string
}

/**
 * Single definition detail returned by GET/POST/PATCH /custom-fields (envelope
 * `data`). Matches `CustomFieldResource`.
 */
export interface CustomFieldDefinitionDetail {
  id: number
  entity_type: string
  key: string
  type: CustomFieldType
  label: string
  description: string | null
  help_text: string | null
  placeholder: string | null
  icon: string | null
  group: string | null
  tab: string | null
  sort_order: number
  default_value: unknown
  config: CustomFieldConfig | null
  validation: CustomFieldValidation | null
  relation_target: CustomFieldRelationTarget | null
  is_indexed: boolean
  is_active: boolean
  options: CustomFieldOptionDetail[]
  created_at: string
}

/** A `CustomFieldDefinitionDetail` carrying the actor's authorization metadata (spec 0004), as returned by `GET /custom-fields/{id}`. */
export interface CustomFieldDefinitionDetailWithPermissions extends CustomFieldDefinitionDetail {
  permissions: ResourcePermissions
}

/** A single ENUM option as sent to the backend (no `id`: full-replace). */
export interface CustomFieldOptionInput {
  value: string
  label: string
  color?: string | null
  icon?: string | null
  sort_order?: number
  is_default?: boolean
}

/**
 * Payload for POST /custom-fields (create). `options` is required/non-empty
 * only when `type` is `enum`; `relation_target` is required/valid only when
 * `type` is `relation` (spec AC-018).
 */
export interface CreateCustomFieldDefinitionPayload {
  entity_type: string
  key: string
  type: CustomFieldType
  label: string
  description?: string | null
  help_text?: string | null
  placeholder?: string | null
  icon?: string | null
  group?: string | null
  tab?: string | null
  sort_order?: number
  default_value?: unknown
  config?: CustomFieldConfig | null
  validation?: CustomFieldValidation | null
  relation_target?: CustomFieldRelationTarget | null
  is_indexed?: boolean
  is_active?: boolean
  options?: CustomFieldOptionInput[]
}

/**
 * Payload for PATCH /custom-fields/{id} (partial update). `options`, when
 * sent, is a full-replace; `entity_type`/`type`/`key` are immutable once the
 * definition has recorded values (server-enforced, spec AC-019).
 */
export type UpdateCustomFieldDefinitionPayload = Partial<CreateCustomFieldDefinitionPayload>

/** Discriminated form mode shared by the definition form hook/meta-resolver (mirrors `AttributeFormMode`). */
export type CustomFieldDefinitionFormMode =
  | { type: 'create' }
  | { type: 'edit'; definition: CustomFieldDefinitionDetailWithPermissions }
