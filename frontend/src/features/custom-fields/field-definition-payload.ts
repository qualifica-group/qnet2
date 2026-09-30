import type {
  CustomFieldConfig,
  CustomFieldOptionInput,
  CustomFieldRelationTarget,
} from '@/features/custom-fields/types'
import { buildTableConfig } from '@/features/custom-fields/field-definition-table'
import type { FieldDefinitionFormValues } from '@/features/custom-fields/field-definition-form-values'

/**
 * Per-type `config`/`relation_target`/`options` write-payload projection,
 * shared by every "field definition" form's payload builder
 * (`custom-field-definition-payload.ts`, `attribute-form-payload.ts`). Both
 * forms keep every type's config fields in one flat bag (simpler RHF
 * wiring); this module is the single seam that projects the relevant subset
 * per `type` onto the wire shape the backend expects, mirroring the
 * backend's `FieldTypeHandler::toMeta()` counterpart.
 */

/** Drops `undefined`/`null`/`''` entries; returns `undefined` when nothing is left (never send an empty config object). */
function omitEmpty<T extends Record<string, unknown>>(input: T): Partial<T> | undefined {
  const entries = Object.entries(input).filter(
    ([, value]) => value !== undefined && value !== null && value !== '',
  )
  return entries.length > 0 ? (Object.fromEntries(entries) as Partial<T>) : undefined
}

export function buildFieldDefinitionConfig(
  values: Pick<FieldDefinitionFormValues, 'type' | 'config' | 'table'>,
): CustomFieldConfig | undefined {
  const config = values.config
  switch (values.type) {
    case 'text':
      return omitEmpty({
        minLength: config.minLength ?? undefined,
        maxLength: config.maxLength ?? undefined,
        regex: config.regex,
        transform: config.transform || undefined,
      })
    case 'textarea':
      return omitEmpty({ rows: config.rows ?? undefined, maxLength: config.maxLength ?? undefined })
    case 'integer':
    case 'decimal':
      return omitEmpty({
        min: config.min ?? undefined,
        max: config.max ?? undefined,
        step: config.step ?? undefined,
        decimals: config.decimals ?? undefined,
      })
    case 'boolean':
    case 'enum':
      // The form types `display` loosely as string; narrow to the config union.
      return omitEmpty({ display: config.display as CustomFieldConfig['display'] })
    case 'table':
      return buildTableConfig(values.table)
    default:
      return undefined
  }
}

export function buildFieldDefinitionRelationTarget(
  values: Pick<FieldDefinitionFormValues, 'type' | 'relation_target'>,
): CustomFieldRelationTarget | undefined {
  if (values.type !== 'relation') {
    return undefined
  }
  return {
    entity_type: values.relation_target.entity_type,
    cardinality: values.relation_target.cardinality,
    for_select_resource: values.relation_target.for_select_resource,
  }
}

/** Maps the form's option rows to the wire shape, assigning `sort_order` from array position. */
export function buildFieldDefinitionOptions(
  values: Pick<FieldDefinitionFormValues, 'type' | 'options'>,
): CustomFieldOptionInput[] | undefined {
  if (values.type !== 'enum') {
    return undefined
  }
  return values.options.map((option, index) => ({
    value: option.value,
    label: option.label,
    color: option.color || undefined,
    icon: option.icon || undefined,
    sort_order: index,
    is_default: option.is_default,
  }))
}
