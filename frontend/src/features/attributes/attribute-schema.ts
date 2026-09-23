import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  fieldDefinitionConfigFields,
  fieldDefinitionOptionFields,
  fieldDefinitionRelationTargetFields,
  fieldDefinitionTypeSchema,
  validateEnumOptions,
  validateRelationTarget,
  type FieldDefinitionSchemaMessages,
} from '@/features/custom-fields/field-definition-schema'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'

/**
 * Zod schema for the attribute create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function (same pattern
 * as `custom-field-definition-schema.ts`). The shape mirrors the frozen
 * backend contract (spec 0017) 1:1: the `type`/`config`/`options`/
 * `relation_target` fields and their enum-requires-options/
 * relation-requires-target cross-field rules are the shared
 * `field-definition-schema.ts` building blocks (mirrored 1:1 by
 * `custom-field-definition-schema.ts`); this file supplies only what an
 * attribute additionally carries (`code`/`name`/`custom_fields`) — no
 * `validation`: the attribute's `required` lives on the category pivot
 * (`attribute_category.is_required`), not on the definition itself.
 */

/** Backend `code` column limit (`max:64`). */
export const CODE_MAX_LENGTH = 64
/** Backend `name`/option `label`/`value` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191
/** Backend `code` shape: snake_case identifier (spec engineering.md §1.2). */
const CODE_PATTERN = /^[a-z][a-z0-9_]*$/

function schemaMessages(t: TFunction): FieldDefinitionSchemaMessages {
  return {
    optionValueRequired: t('attributes.form.optionValueRequired'),
    optionValueMax: t('attributes.form.optionValueMax'),
    optionLabelRequired: t('attributes.form.optionLabelRequired'),
    optionLabelMax: t('attributes.form.optionLabelMax'),
    optionsRequiredForEnum: t('attributes.form.optionsRequiredForEnum'),
    optionValuesDuplicate: t('attributes.form.optionValuesDuplicate'),
    relationEntityTypeRequired: t('attributes.form.relationEntityTypeRequired'),
    relationForSelectResourceRequired: t('attributes.form.relationForSelectResourceRequired'),
  }
}

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    code: z
      .string()
      .min(1, t('attributes.form.codeRequired'))
      .max(CODE_MAX_LENGTH, t('attributes.form.codeMax'))
      .regex(CODE_PATTERN, t('attributes.form.codeInvalid')),
    name: z
      .string()
      .min(1, t('attributes.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('attributes.form.nameMax')),
    type: fieldDefinitionTypeSchema,
    description: z.string(),
    help_text: z.string(),
    placeholder: z.string(),
    icon: z.string(),
    config: fieldDefinitionConfigFields(),
    relation_target: fieldDefinitionRelationTargetFields(),
    options: z.array(fieldDefinitionOptionFields(schemaMessages(t), NAME_MAX_LENGTH)),
  }
}

/** Create schema. Edit reuses the exact same shape (spec: full-replace PATCH). `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateAttributeSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  const messages = schemaMessages(t)
  return z
    .object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
    .superRefine((values, ctx) => {
      if (values.type === 'enum') {
        validateEnumOptions(values.options, messages, ctx)
      }
      if (values.type === 'relation') {
        validateRelationTarget(values.relation_target, messages, ctx)
      }
    })
}

export function buildUpdateAttributeSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return buildCreateAttributeSchema(t, customFieldsSchema)
}

export type CreateAttributeFormValues = z.infer<ReturnType<typeof buildCreateAttributeSchema>>
export type UpdateAttributeFormValues = CreateAttributeFormValues
