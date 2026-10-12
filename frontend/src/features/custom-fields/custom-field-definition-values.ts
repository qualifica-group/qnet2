import {
  emptyFieldDefinitionValues,
  hydrateFieldDefinitionValues,
} from '@/features/custom-fields/field-definition-defaults'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'
import type { CustomFieldDefinitionDetail, CustomFieldValidation } from '@/features/custom-fields/types'

function emptyValidation() {
  return {
    required: false,
    unique: false,
    min: null,
    max: null,
    regex: '',
    email: false,
    url: false,
    exists: false,
    distinct: false,
  }
}

/** Blank values of a brand-new definition (the create form's draft). */
export function emptyDefinitionValues(): CustomFieldDefinitionFormValues {
  return {
    ...emptyFieldDefinitionValues(),
    entity_type: '',
    key: '',
    label: '',
    group: '',
    tab: '',
    sort_order: 0,
    is_indexed: false,
    is_active: true,
    validation: emptyValidation(),
  }
}

/** Hydrates the flat config/validation bag from a persisted definition, defaulting every field the type does not use. */
export function hydrateDefinitionValues(definition: CustomFieldDefinitionDetail): CustomFieldDefinitionFormValues {
  const validation = definition.validation as (CustomFieldValidation & Record<string, unknown>) | null

  return {
    ...hydrateFieldDefinitionValues(definition),
    entity_type: definition.entity_type,
    key: definition.key,
    label: definition.label,
    group: definition.group ?? '',
    tab: definition.tab ?? '',
    sort_order: definition.sort_order,
    is_indexed: definition.is_indexed,
    is_active: definition.is_active,
    validation: {
      ...emptyValidation(),
      required: Boolean(validation?.required),
      unique: Boolean(validation?.unique),
      min: validation?.min ?? null,
      max: validation?.max ?? null,
      regex: validation?.regex ?? '',
      email: Boolean(validation?.email),
      url: Boolean(validation?.url),
      exists: Boolean(validation?.exists),
      distinct: Boolean(validation?.distinct),
    },
  }
}
