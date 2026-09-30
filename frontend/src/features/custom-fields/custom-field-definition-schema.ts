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
  fieldDefinitionTableFields,
  validateTableDefinition,
} from '@/features/custom-fields/field-definition-table-schema'

/**
 * Zod schema for the custom field DEFINITION admin form (spec 0021 AC-025),
 * built as a factory so validation messages are localized (same pattern as
 * `attribute-schema.ts`). The shape mirrors the frozen backend contract
 * (`StoreCustomFieldRequest`/`UpdateCustomFieldRequest`) 1:1: the `type`/
 * `config`/`options`/`relation_target` fields and their enum-requires-options/
 * relation-requires-target cross-field rules are the shared
 * `field-definition-schema.ts` building blocks (mirrored 1:1 by
 * `attribute-schema.ts`); this file supplies only what a custom field
 * definition additionally carries (`entity_type`/`key`/`label`/`group`/`tab`/
 * `sort_order`/`is_indexed`/`is_active`/`validation`).
 */

/** Backend `key` column limit (`max:64`), snake_case identifier. */
const KEY_MAX_LENGTH = 64
/** Backend `label`/`options.*.value`/`options.*.label` column limit (`max:191`). */
const LABEL_MAX_LENGTH = 191
/** Backend `key` shape: `regex:/^[a-z0-9_]+$/`. */
const KEY_PATTERN = /^[a-z0-9_]+$/

function schemaMessages(t: TFunction): FieldDefinitionSchemaMessages {
  return {
    optionValueRequired: t('customFields.form.optionValueRequired'),
    optionValueMax: t('customFields.form.optionValueMax'),
    optionLabelRequired: t('customFields.form.optionLabelRequired'),
    optionLabelMax: t('customFields.form.optionLabelMax'),
    optionsRequiredForEnum: t('customFields.form.optionsRequiredForEnum'),
    optionValuesDuplicate: t('customFields.form.optionValuesDuplicate'),
    relationEntityTypeRequired: t('customFields.form.relationEntityTypeRequired'),
    relationForSelectResourceRequired: t('customFields.form.relationForSelectResourceRequired'),
  }
}

/** Validation-builder bag (`required`/`unique`/`min`/`max`/`regex`/`email`/`url`/`exists`/`distinct`). */
function validationFields() {
  return z.object({
    required: z.boolean(),
    unique: z.boolean(),
    min: z.number().nullable(),
    max: z.number().nullable(),
    regex: z.string(),
    email: z.boolean(),
    url: z.boolean(),
    exists: z.boolean(),
    distinct: z.boolean(),
  })
}

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    entity_type: z.string().min(1, t('customFields.form.entityTypeRequired')),
    key: z
      .string()
      .min(1, t('customFields.form.keyRequired'))
      .max(KEY_MAX_LENGTH, t('customFields.form.keyMax'))
      .regex(KEY_PATTERN, t('customFields.form.keyInvalid')),
    type: fieldDefinitionTypeSchema,
    label: z
      .string()
      .min(1, t('customFields.form.labelRequired'))
      .max(LABEL_MAX_LENGTH, t('customFields.form.labelMax')),
    description: z.string(),
    help_text: z.string(),
    placeholder: z.string(),
    icon: z.string(),
    group: z.string(),
    tab: z.string(),
    sort_order: z.number().int(),
    is_indexed: z.boolean(),
    is_active: z.boolean(),
    config: fieldDefinitionConfigFields(),
    validation: validationFields(),
    relation_target: fieldDefinitionRelationTargetFields(),
    options: z.array(fieldDefinitionOptionFields(schemaMessages(t), LABEL_MAX_LENGTH)),
    table: fieldDefinitionTableFields(),
  }
}

/** Create schema. Edit reuses the exact same shape (spec: `sometimes` PATCH, diffed client-side). */
export function buildCreateCustomFieldDefinitionSchema(t: TFunction) {
  const messages = schemaMessages(t)
  return z.object({ ...baseFields(t) }).superRefine((values, ctx) => {
    if (values.type === 'enum') {
      validateEnumOptions(values.options, messages, ctx)
    }
    if (values.type === 'relation') {
      validateRelationTarget(values.relation_target, messages, ctx)
    }
    if (values.type === 'table') {
      validateTableDefinition(values.table, t, ctx)
    }
  })
}

export function buildUpdateCustomFieldDefinitionSchema(t: TFunction) {
  return buildCreateCustomFieldDefinitionSchema(t)
}

export type CustomFieldDefinitionFormValues = z.infer<
  ReturnType<typeof buildCreateCustomFieldDefinitionSchema>
>
