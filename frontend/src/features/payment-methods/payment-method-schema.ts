import { z } from 'zod'
import type { TFunction } from 'i18next'
import { VAT_ALLOCATIONS, type VatAllocation } from '@/features/payment-methods/types'

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
/** Backend installment bounds (spec 0194 D-10). */
const INSTALLMENTS_MIN = 1
const INSTALLMENTS_MAX = 60
const DAYS_BETWEEN_MIN = 0
const DAYS_BETWEEN_MAX = 365
const EXTRA_DAYS_MIN = 0
const EXTRA_DAYS_MAX = 31

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
    installments_count: z
      .number()
      .int(t('paymentMethods.form.installmentsCountInvalid'))
      .min(INSTALLMENTS_MIN, t('paymentMethods.form.installmentsCountMin'))
      .max(INSTALLMENTS_MAX, t('paymentMethods.form.installmentsCountMax')),
    days_between_installments: z
      .number()
      .int(t('paymentMethods.form.daysBetweenInvalid'))
      .min(DAYS_BETWEEN_MIN, t('paymentMethods.form.daysBetweenMin'))
      .max(DAYS_BETWEEN_MAX, t('paymentMethods.form.daysBetweenMax'))
      .nullable(),
    end_of_month: z.boolean(),
    end_of_month_extra_days: z
      .number()
      .int(t('paymentMethods.form.extraDaysInvalid'))
      .min(EXTRA_DAYS_MIN, t('paymentMethods.form.extraDaysMin'))
      .max(EXTRA_DAYS_MAX, t('paymentMethods.form.extraDaysMax'))
      .nullable(),
    vat_allocation: z.enum(VAT_ALLOCATIONS),
    is_active: z.boolean(),
  }
}

interface RateFields {
  installments_count: number
  days_between_installments: number | null
  vat_allocation: VatAllocation
}

/** Cross-field rules mirroring the server (spec 0194 D-10). */
function refineRates(values: RateFields, ctx: z.RefinementCtx, t: TFunction) {
  if (values.installments_count > 1 && values.days_between_installments === null) {
    ctx.addIssue({
      code: 'custom',
      path: ['days_between_installments'],
      message: t('paymentMethods.form.daysBetweenRequired'),
    })
  }
  if (values.vat_allocation === 'vat_first' && values.installments_count < 2) {
    ctx.addIssue({
      code: 'custom',
      path: ['vat_allocation'],
      message: t('paymentMethods.form.vatFirstNeedsInstallments'),
    })
  }
}

/** Create schema. */
export function buildCreatePaymentMethodSchema(t: TFunction) {
  return z.object(baseFields(t)).superRefine((values, ctx) => refineRates(values, ctx, t))
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdatePaymentMethodSchema(t: TFunction) {
  return z.object(baseFields(t)).superRefine((values, ctx) => refineRates(values, ctx, t))
}

export type CreatePaymentMethodFormValues = z.infer<
  ReturnType<typeof buildCreatePaymentMethodSchema>
>
export type UpdatePaymentMethodFormValues = z.infer<
  ReturnType<typeof buildUpdatePaymentMethodSchema>
>
