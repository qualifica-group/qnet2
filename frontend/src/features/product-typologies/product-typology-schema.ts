import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  SUPPLIER_COMMISSION_DIRECTIONS,
  type SupplierCommissionDirection,
} from '@/features/product-typologies/types'

interface DirectionFields {
  supplier_commission_enabled: boolean
  supplier_commission_direction: SupplierCommissionDirection | null
}

/**
 * Zod schema for the product typology create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0099) 1:1. `code` keeps the same
 * regex/max shape in both create and edit — on edit the field renders
 * disabled (readonly per the backend's field permissions, D-2) and
 * `buildUpdatePayload` never emits it, so the value here is always the
 * server's own, already-valid one.
 */

/** Backend `name` column limit (`max:191`, unique). */
const NAME_MAX_LENGTH = 191
/** Backend `code` column limit (`max:64`). */
const CODE_MAX_LENGTH = 64
/** Backend `code` shape: snake_case identifier (spec engineering.md §1.2). */
const CODE_PATTERN = /^[a-z][a-z0-9_]*$/
/** Backend `description` column limit (`max:500`). */
const DESCRIPTION_MAX_LENGTH = 500

/** Shared fields common to create and edit. */
function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('productTypologies.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('productTypologies.form.nameMax')),
    code: z
      .string()
      .min(1, t('productTypologies.form.codeRequired'))
      .max(CODE_MAX_LENGTH, t('productTypologies.form.codeMax'))
      .regex(CODE_PATTERN, t('productTypologies.form.codeInvalid')),
    description: z
      .string()
      .max(DESCRIPTION_MAX_LENGTH, t('productTypologies.form.descriptionMax'))
      .nullable(),
    supplier_commission_enabled: z.boolean(),
    supplier_commission_direction: z.enum(SUPPLIER_COMMISSION_DIRECTIONS).nullable(),
  }
}

/** Direction is mandatory when the Supplier commission is enabled (spec 0202 D-7). */
function requireDirectionWhenEnabled<T extends z.ZodType<DirectionFields>>(schema: T, t: TFunction) {
  return schema.refine(
    (values) => !values.supplier_commission_enabled || values.supplier_commission_direction !== null,
    {
      message: t('productTypologies.form.supplierCommissionDirectionRequired'),
      path: ['supplier_commission_direction'],
    },
  )
}

/** Create schema. */
export function buildCreateProductTypologySchema(t: TFunction) {
  return requireDirectionWhenEnabled(z.object(baseFields(t)), t)
}

/** Edit schema (same shape; partial PATCH is computed by the caller). */
export function buildUpdateProductTypologySchema(t: TFunction) {
  return requireDirectionWhenEnabled(z.object(baseFields(t)), t)
}

export type CreateProductTypologyFormValues = z.infer<
  ReturnType<typeof buildCreateProductTypologySchema>
>
export type UpdateProductTypologyFormValues = z.infer<
  ReturnType<typeof buildUpdateProductTypologySchema>
>
