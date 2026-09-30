import type {
  FieldDefinitionFormValues,
  FieldDefinitionConfigBag,
  FieldDefinitionOptionRow,
  FieldDefinitionRelationTargetBag,
} from '@/features/custom-fields/field-definition-form-values'
import { emptyTableDefinition, hydrateTableDefinition } from '@/features/custom-fields/field-definition-table'
import type { CustomFieldConfig, CustomFieldRelationTarget, CustomFieldType } from '@/features/custom-fields/types'

/**
 * Default-value and edit-mode hydration helpers shared by every "field
 * definition" form hook (`useCustomFieldDefinitionForm`, `useAttributeForm`):
 * the blank/empty bag each type-conditional sub-form starts from, and the
 * mapping from a persisted definition's `config`/`relation_target`/`options`
 * (loosely typed JSON) back onto the form's flat bag.
 */

export function emptyFieldDefinitionConfig(): FieldDefinitionConfigBag {
  return {
    minLength: null,
    maxLength: null,
    regex: '',
    transform: '',
    rows: null,
    min: null,
    max: null,
    step: null,
    decimals: null,
    display: '',
  }
}

export function emptyFieldDefinitionRelationTarget(): FieldDefinitionRelationTargetBag {
  return { entity_type: '', cardinality: 'one', for_select_resource: '' }
}

/** A single blank ENUM option row appended by the "Add option" affordance. */
export function blankFieldDefinitionOption(): FieldDefinitionOptionRow {
  return { value: '', label: '', color: '', icon: '', is_default: false }
}

/** Blank values for a brand-new field definition (create mode). */
export function emptyFieldDefinitionValues(): FieldDefinitionFormValues {
  return {
    type: 'text',
    description: '',
    help_text: '',
    placeholder: '',
    icon: '',
    config: emptyFieldDefinitionConfig(),
    relation_target: emptyFieldDefinitionRelationTarget(),
    options: [],
    table: emptyTableDefinition(),
  }
}

/** The persisted-definition subset {@link hydrateFieldDefinitionValues} reads from. */
export interface HydratableFieldDefinition {
  type: CustomFieldType
  description: string | null
  help_text: string | null
  placeholder: string | null
  icon: string | null
  config: CustomFieldConfig | null
  relation_target: CustomFieldRelationTarget | null
  options: {
    value: string
    label: string
    color: string | null
    icon: string | null
    sort_order: number
    is_default: boolean
  }[]
}

/** Hydrates the flat config bag from a persisted `config` JSON, defaulting every field the type does not use. */
function hydrateConfig(config: CustomFieldConfig | null): FieldDefinitionConfigBag {
  const bag = config as Record<string, unknown> | null
  return {
    ...emptyFieldDefinitionConfig(),
    minLength: (bag?.minLength as number | undefined) ?? null,
    maxLength: (bag?.maxLength as number | undefined) ?? null,
    regex: (bag?.regex as string | undefined) ?? '',
    transform: (bag?.transform as FieldDefinitionConfigBag['transform'] | undefined) ?? '',
    rows: (bag?.rows as number | undefined) ?? null,
    min: (bag?.min as number | undefined) ?? null,
    max: (bag?.max as number | undefined) ?? null,
    step: (bag?.step as number | undefined) ?? null,
    decimals: (bag?.decimals as number | undefined) ?? null,
    display: (bag?.display as string | undefined) ?? '',
  }
}

/** Hydrates the flat config/relation_target/options bag from a persisted definition (edit mode). */
export function hydrateFieldDefinitionValues(
  source: HydratableFieldDefinition,
): FieldDefinitionFormValues {
  return {
    type: source.type,
    description: source.description ?? '',
    help_text: source.help_text ?? '',
    placeholder: source.placeholder ?? '',
    icon: source.icon ?? '',
    config: hydrateConfig(source.config),
    relation_target: {
      entity_type: source.relation_target?.entity_type ?? '',
      cardinality: source.relation_target?.cardinality ?? 'one',
      for_select_resource: source.relation_target?.for_select_resource ?? '',
    },
    options:
      source.options.length > 0
        ? [...source.options]
            .sort((a, b) => a.sort_order - b.sort_order)
            .map((option) => ({
              value: option.value,
              label: option.label,
              color: option.color ?? '',
              icon: option.icon ?? '',
              is_default: option.is_default,
            }))
        : [],
    table: hydrateTableDefinition(source.config),
  }
}
