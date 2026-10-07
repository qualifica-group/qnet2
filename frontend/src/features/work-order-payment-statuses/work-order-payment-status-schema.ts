import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the work order payment status create/edit form, built as a
 * factory so messages are localized. Mirrors the backend contract (spec 0201)
 * 1:1: `color` stores a palette TOKEN and is required; `description` is
 * nullable free text; `is_active` and `allows_delivery` are booleans.
 * `sort_order` is server-managed and has no form field.
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Backend `color` column limit (`max:32`). */
const COLOR_MAX_LENGTH = 32

/** Backend `description` column limit (`max:500`). */
const DESCRIPTION_MAX_LENGTH = 500

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('workOrderPaymentStatuses.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('workOrderPaymentStatuses.form.nameMax')),
    description: z
      .string()
      .max(DESCRIPTION_MAX_LENGTH, t('workOrderPaymentStatuses.form.descriptionMax'))
      .nullable(),
    color: z
      .string()
      .min(1, t('workOrderPaymentStatuses.form.colorRequired'))
      .max(COLOR_MAX_LENGTH, t('workOrderPaymentStatuses.form.colorMax')),
    is_active: z.boolean(),
    allows_delivery: z.boolean(),
  }
}

/** Create schema. */
export function buildCreateWorkOrderPaymentStatusSchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateWorkOrderPaymentStatusSchema(t: TFunction) {
  return z.object(baseFields(t))
}

export type CreateWorkOrderPaymentStatusFormValues = z.infer<
  ReturnType<typeof buildCreateWorkOrderPaymentStatusSchema>
>
export type UpdateWorkOrderPaymentStatusFormValues = z.infer<
  ReturnType<typeof buildUpdateWorkOrderPaymentStatusSchema>
>
