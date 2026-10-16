import { z } from 'zod'
import type { TFunction } from 'i18next'
import { BUSINESS_FUNCTION_TYPES } from '@/features/business-functions/types'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'

/**
 * Zod schemas for the business-function create/edit form, built as factories
 * so validation messages are localized via the i18n `t` function (same
 * pattern as `users`/`roles`). The shapes mirror the frozen backend contract
 * (spec 0010) 1:1.
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

const businessFunctionTypeSchema = z.enum(BUSINESS_FUNCTION_TYPES)

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('businessFunctions.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('businessFunctions.form.nameMax')),
    // Mutually exclusive domain type; `null` maps to "none" (both boolean
    // columns cleared server-side).
    type: businessFunctionTypeSchema.nullable(),
    // Single-select responsabile (for-select standard): `null` = unset.
    manager_id: z.number().nullable(),
    // Multiselect associated users (for-select standard): ids, full-replace.
    users: z.array(z.number()),
    // Single-select parent function (for-select standard): `null` = top-level.
    // The anti-cycle rule (self/descendant) is enforced server-side (422).
    parent_id: z.number().nullable(),
    // Multiselect operational sites (for-select standard): ids, full-replace.
    operational_sites: z.array(z.number()),
    // Multiselect companies (for-select standard): ids, full-replace.
    company_ids: z.array(z.number()),
  }
}

/** Create schema. `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateBusinessFunctionSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateBusinessFunctionSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

export type CreateBusinessFunctionFormValues = z.infer<
  ReturnType<typeof buildCreateBusinessFunctionSchema>
>
export type UpdateBusinessFunctionFormValues = z.infer<
  ReturnType<typeof buildUpdateBusinessFunctionSchema>
>
