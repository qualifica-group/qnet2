import type { CustomFieldType } from '@/features/custom-fields/types'
import type { TableDefinitionBag } from '@/features/custom-fields/field-definition-table'

/**
 * Minimal form-values shape shared by every "field definition" form: the
 * custom field admin CRUD (spec 0021) and the attribute CRUD (spec 0017) —
 * both let an admin pick a `type` from the same 13-entry `FieldTypeRegistry`
 * and configure its per-type config/options/relation_target/presentation the
 * same way. Each concrete form (`CustomFieldDefinitionFormValues`,
 * `CreateAttributeFormValues`) extends this with its own identity/flags
 * fields; the shared sub-editors (`DefinitionTypePicker`,
 * `DefinitionTypeConfigFields`, `DefinitionOptionsEditor`,
 * `DefinitionRelationTargetEditor`, `DefinitionFieldPreview`,
 * `DefinitionPresentationFields`) are written against THIS type only, so both
 * forms mount the exact same components — no duplicated sub-editors.
 */

/** Loose, per-type-optional config bag (mirrors `CustomFieldConfig`, flattened for RHF). */
export interface FieldDefinitionConfigBag {
  minLength: number | null
  maxLength: number | null
  regex: string
  transform: '' | 'upper' | 'lower' | 'capitalize'
  rows: number | null
  min: number | null
  max: number | null
  step: number | null
  decimals: number | null
  display: string
}

export interface FieldDefinitionRelationTargetBag {
  entity_type: string
  cardinality: 'one' | 'many'
  for_select_resource: string
}

export interface FieldDefinitionOptionRow {
  value: string
  label: string
  color: string
  icon: string
  is_default: boolean
}

export interface FieldDefinitionFormValues {
  type: CustomFieldType
  description: string
  help_text: string
  placeholder: string
  icon: string
  config: FieldDefinitionConfigBag
  relation_target: FieldDefinitionRelationTargetBag
  options: FieldDefinitionOptionRow[]
  /** Only meaningful when `type === 'table'` (spec 0180). */
  table: TableDefinitionBag
}
