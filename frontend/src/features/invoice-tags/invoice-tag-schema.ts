import { z } from 'zod'
import type { TFunction } from 'i18next'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'

/**
 * Zod schema for the invoice tag create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0247 `data_contract`) 1:1.
 * `color` stores a palette TOKEN and is REQUIRED; it is checked against the
 * same allow-list the backend validates with, so this is defense in depth,
 * not the only guard.
 * `sort_order` is server-managed and has no form field.
 */

/** Backend `name` column limit (`max:191`, unique). */
const NAME_MAX_LENGTH = 191

/** Backend `description` column limit (`max:500`). */
const DESCRIPTION_MAX_LENGTH = 500

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('invoiceTags.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('invoiceTags.form.nameMax')),
    description: z
      .string()
      .max(DESCRIPTION_MAX_LENGTH, t('invoiceTags.form.descriptionMax'))
      .nullable(),
    color: z
      .string()
      .min(1, t('invoiceTags.form.colorRequired'))
      .refine((token) => swatchClassFor(token) !== undefined, t('invoiceTags.form.colorInvalid')),
    is_active: z.boolean(),
  }
}

/** Create schema. */
export function buildCreateInvoiceTagSchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema (same shape; the partial PATCH is computed by the caller). */
export function buildUpdateInvoiceTagSchema(t: TFunction) {
  return z.object(baseFields(t))
}

export type CreateInvoiceTagFormValues = z.infer<ReturnType<typeof buildCreateInvoiceTagSchema>>
export type UpdateInvoiceTagFormValues = z.infer<ReturnType<typeof buildUpdateInvoiceTagSchema>>
