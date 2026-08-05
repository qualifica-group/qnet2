import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema for the payment method create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0068) 1:1. `code` keeps the same
 * regex/max shape in both create and edit — on edit the field renders
 * disabled (readonly per the backend's field permissions, D-3) and
 * `buildUpdatePayload` never emits it, so the value here is always the
 * server's own, already-valid one. `payment_days` is a nullable integer
 * (0..3650), mirroring the `vat-rates` numeric-nullable pattern.
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191
/** Backend `code` column limit (`max:64`). */
const CODE_MAX_LENGTH = 64
/** Backend `code` shape: snake_case identifier (spec engineering.md §1.2). */
const CODE_PATTERN = /^[a-z][a-z0-9_]*$/
/** Backend `payment_method_code` column limit (`max:32`). */
const PAYMENT_METHOD_CODE_MAX_LENGTH = 32
/** Backend `description` column limit (`max:500`). */
const DESCRIPTION_MAX_LENGTH = 500
/** Backend `payment_instructions` column limit (`max:5000`). */
const PAYMENT_INSTRUCTIONS_MAX_LENGTH = 5000
/** Backend `payment_days` bounds (`min:0`, `max:3650`). */
const PAYMENT_DAYS_MIN = 0
const PAYMENT_DAYS_MAX = 3650

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('paymentMethods.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('paymentMethods.form.nameMax')),
    code: z
      .string()
      .min(1, t('paymentMethods.form.codeRequired'))
      .max(CODE_MAX_LENGTH, t('paymentMethods.form.codeMax'))
      .regex(CODE_PATTERN, t('paymentMethods.form.codeInvalid')),
    payment_method_code: z
      .string()
      .max(PAYMENT_METHOD_CODE_MAX_LENGTH, t('paymentMethods.form.paymentMethodCodeMax'))
      .nullable(),
    description: z
      .string()
      .max(DESCRIPTION_MAX_LENGTH, t('paymentMethods.form.descriptionMax'))
      .nullable(),
    payment_instructions: z
      .string()
      .max(PAYMENT_INSTRUCTIONS_MAX_LENGTH, t('paymentMethods.form.paymentInstructionsMax'))
      .nullable(),
    payment_days: z
      .number()
      .int(t('paymentMethods.form.paymentDaysInvalid'))
      .min(PAYMENT_DAYS_MIN, t('paymentMethods.form.paymentDaysMin'))
      .max(PAYMENT_DAYS_MAX, t('paymentMethods.form.paymentDaysMax'))
      .nullable(),
    is_active: z.boolean(),
  }
}

/** Create schema. */
export function buildCreatePaymentMethodSchema(t: TFunction) {
  return z.object(baseFields(t))
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdatePaymentMethodSchema(t: TFunction) {
  return z.object(baseFields(t))
}

export type CreatePaymentMethodFormValues = z.infer<
  ReturnType<typeof buildCreatePaymentMethodSchema>
>
export type UpdatePaymentMethodFormValues = z.infer<
  ReturnType<typeof buildUpdatePaymentMethodSchema>
>
