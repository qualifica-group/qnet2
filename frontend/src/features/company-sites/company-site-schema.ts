import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'

/** Backend column limit (spec 0020 `data_contract`). */
const NAME_MAX_LENGTH = 191

/**
 * Shared fields common to create and edit. The anagraphic (identity card +
 * contacts + single address) is NOT part of this schema: like the Registries
 * module, it is a buffered `PersonalDataDraft` owned by the form hook and
 * validated separately via `buildPersonalDataSchema`. Only the site's own
 * scalar `name` plus the Impostazioni-tab fields live here.
 */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('companySites.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('companySites.form.nameMax')),
    notes: z.string().optional(),
    // Settings tab: the owning company. The preferred bank is a per-row flag
    // on the banks list, not a field here.
    company_id: z.number().nullable(),
  }
}

/** Create schema. `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateCompanySiteSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateCompanySiteSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

export type CreateCompanySiteFormValues = z.infer<
  ReturnType<typeof buildCreateCompanySiteSchema>
>
export type UpdateCompanySiteFormValues = z.infer<
  ReturnType<typeof buildUpdateCompanySiteSchema>
>
