import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'
import { QUALIFICATION_TYPES, RELATIONSHIP_TYPES } from '@/features/users/types'

/**
 * Zod schemas for the user create/edit form, built as factories so validation
 * messages are localized via the i18n `t` function (same pattern as the auth
 * forms). The shapes mirror the frozen backend contract 1:1.
 */

/** Minimum password length enforced client-side as an affordance. */
const PASSWORD_MIN_LENGTH = 8

/** Contract bound (spec 0015): a daily duration never exceeds a full day. */
const MAX_DAILY_MINUTES = 1440

const localeSchema = z.enum(['en', 'it'])

/**
 * Employment sub-schema (spec 0015). Text/date fields mirror the personal-data
 * card convention: RHF holds `''` for "empty" (never `null`), converted to
 * `null` at the payload boundary; relation ids and enums hold `null` directly
 * (the AsyncPaginatedSelect/Select convention).
 */
function buildEmploymentSchema(t: TFunction) {
  return z
    .object({
      is_manager: z.boolean(),
      job_description: z.string().max(255, t('users.form.employment.jobDescriptionMax')),
      reports_to_id: z.number().nullable(),
      business_function_id: z.number().nullable(),
      relationship_type: z.enum(RELATIONSHIP_TYPES).nullable(),
      company_id: z.number().nullable(),
      // Site membership (spec 0103): at most one physical site, plus zero or
      // more remote sites — both operative to the same effect (D-1).
      primary_operational_site_id: z.number().nullable(),
      remote_operational_site_ids: z.array(z.number()),
      // Assignment competence (spec 0110): the product categories the user is
      // competent for, on the same per-field tri-state as the remote sites.
      product_category_ids: z.array(z.number()),
      qualification_type: z.enum(QUALIFICATION_TYPES).nullable(),
      hired_at: z.string(),
      terminated_at: z.string(),
      standard_daily_minutes: z.number().int().min(0).max(MAX_DAILY_MINUTES).nullable(),
      break_daily_minutes: z.number().int().min(0).max(MAX_DAILY_MINUTES).nullable(),
    })
    .superRefine((values, ctx) => {
      if (values.hired_at && values.terminated_at && values.terminated_at < values.hired_at) {
        ctx.addIssue({
          code: 'custom',
          path: ['terminated_at'],
          message: t('users.form.employment.terminatedBeforeHiredAt'),
        })
      }
    })
}

export type EmploymentFormValues = z.infer<ReturnType<typeof buildEmploymentSchema>>

/**
 * Shared scalar fields common to create and edit. The user's display `name` is
 * NOT here: it is derived server-side from the personal-data card (single source
 * of truth), never entered on the user form.
 */
function baseFields(t: TFunction) {
  return {
    email: z
      .string()
      .min(1, t('users.form.emailRequired'))
      .email(t('users.form.emailInvalid')),
    locale: localeSchema,
    // Whether the account may sign in; an inactive user is denied login.
    is_active: z.boolean(),
    // Role IDS (for-select standard, ADR 0011): the picker submits ids.
    roles: z.array(z.number()),
    // Employment profile (spec 0015): always present, upserted on submit.
    employment: buildEmploymentSchema(t),
  }
}

/**
 * Create schema: password is required and must match its confirmation.
 * `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021).
 */
export function buildCreateUserSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z
    .object({
      ...baseFields(t),
      password: z.string().min(PASSWORD_MIN_LENGTH, t('users.form.passwordMinLength')),
      password_confirmation: z.string().min(1, t('users.form.confirmPasswordRequired')),
      custom_fields: asCustomFieldsField(customFieldsSchema),
    })
    .refine((values) => values.password === values.password_confirmation, {
      path: ['password_confirmation'],
      message: t('users.form.passwordsDontMatch'),
    })
}

/**
 * Edit schema: password is optional (PATCH). When provided it must satisfy the
 * length rule and match its confirmation; when left blank both fields are empty
 * and ignored by the caller before sending the PATCH.
 */
export function buildUpdateUserSchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z
    .object({
      ...baseFields(t),
      password: z
        .string()
        .refine((value) => value === '' || value.length >= PASSWORD_MIN_LENGTH, {
          message: t('users.form.passwordMinLength'),
        }),
      password_confirmation: z.string(),
      custom_fields: asCustomFieldsField(customFieldsSchema),
    })
    .refine((values) => values.password === values.password_confirmation, {
      path: ['password_confirmation'],
      message: t('users.form.passwordsDontMatch'),
    })
}

export type CreateUserFormValues = z.infer<ReturnType<typeof buildCreateUserSchema>>
export type UpdateUserFormValues = z.infer<ReturnType<typeof buildUpdateUserSchema>>
