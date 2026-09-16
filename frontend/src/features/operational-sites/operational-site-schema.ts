import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'

/**
 * Zod schemas for the operational-site create/edit form, built as factories
 * so validation messages are localized via the i18n `t` function (same
 * pattern as `users`/`business-functions`). The shapes mirror the frozen
 * backend contract (spec 0011) 1:1.
 */

/** Backend `line1`/`postal_code`/`alias` column limits (spec 0011 data_contract). */
const LINE1_MAX_LENGTH = 255
const POSTAL_CODE_MAX_LENGTH = 20
const ALIAS_MAX_LENGTH = 255

/**
 * Shared fields common to create and edit. `city_id` is the only mandatory
 * geo id (spec 0011 meta contract): it stays typed `number | null` — like the
 * rest of the cascade — because `GeoSelect` keeps every level nullable until
 * chosen, but a plain `refine` rejects the unset state at submit time.
 */
function baseFields(t: TFunction) {
  return {
    alias: z.string().max(ALIAS_MAX_LENGTH, t('operationalSites.form.aliasMax')).optional(),
    line1: z
      .string()
      .min(1, t('operationalSites.form.line1Required'))
      .max(LINE1_MAX_LENGTH, t('operationalSites.form.line1Max')),
    postal_code: z.string().max(POSTAL_CODE_MAX_LENGTH, t('operationalSites.form.postalCodeMax')).optional(),
    country_id: z.number().nullable(),
    state_id: z.number().nullable(),
    province_id: z.number().nullable(),
    city_id: z
      .number()
      .nullable()
      // Explicit `boolean` return annotation: without it, TS 5.5+'s automatic
      // type-predicate inference turns this plain null-check into an implicit
      // `value is number`, which silently narrows `z.infer<>` to non-nullable
      // — contradicting the comment above (`city_id` must stay `number |
      // null` in the TYPE; only the runtime validation rejects `null`).
      .refine((value): boolean => value !== null, { message: t('operationalSites.form.cityRequired') }),
    is_active: z.boolean(),
  }
}

/** Create schema. `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateOperationalSiteSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateOperationalSiteSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

export type CreateOperationalSiteFormValues = z.infer<
  ReturnType<typeof buildCreateOperationalSiteSchema>
>
export type UpdateOperationalSiteFormValues = z.infer<
  ReturnType<typeof buildUpdateOperationalSiteSchema>
>
