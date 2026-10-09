import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'

/**
 * Zod schema for the sector create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0018) 1:1; the anti-cycle
 * `parent_id` rule is enforced server-side (the picker also excludes the
 * sector's own subtree client-side as a UX affordance, not a validity gate).
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('sectors.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('sectors.form.nameMax')),
    parent_id: z.number().nullable(),
    is_active: z.boolean(),
  }
}

/** `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateSectorSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

export function buildUpdateSectorSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

export type CreateSectorFormValues = z.infer<ReturnType<typeof buildCreateSectorSchema>>
export type UpdateSectorFormValues = CreateSectorFormValues
