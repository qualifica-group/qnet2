import type { AttributeFormValues } from '@/features/attributes/use-attribute-form'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'
import { emptyDefinitionValues } from '@/features/custom-fields/custom-field-definition-values'

/**
 * The shared closed-row summaries read a custom field definition's values: an
 * attribute has the same type/config/options/relation/table bag, so the rest
 * (module, key, validation) is filled with blanks that no summary here reads.
 */
export function toDefinitionValues(values: AttributeFormValues): CustomFieldDefinitionFormValues {
  return { ...emptyDefinitionValues(), ...values }
}
