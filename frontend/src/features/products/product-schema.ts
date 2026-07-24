import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'
import { isEmptyCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/**
 * Zod schema for the product create/edit form's GENERIC fields, built as a
 * factory so validation messages are localized via the i18n `t` function.
 * `attribute_values` (spec 0061) is additive: one entry per the selected
 * category's PRODUCT-context effective attribute, mirroring
 * `request-work-schema.ts`'s `buildAttributeValuesSchema` (kept independent
 * since that file must not be touched — the Opportunity path's invariant).
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('products.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('products.form.nameMax')),
    description: z.string().nullable(),
    // cost/price/category_id are held nullable so the controlled inputs can
    // represent "empty"; the required-value superRefine below rejects a null
    // at submit, mirroring the backend's `required` rules.
    cost: z.number().nonnegative(t('products.form.costInvalid')).nullable(),
    price: z.number().nonnegative(t('products.form.priceInvalid')).nullable(),
    category_id: z.number().nullable(),
    product_type: z.enum(['SERVICE']),
    vat_rate_id: z.number().nullable(),
    supplier_id: z.number().nullable(),
  }
}

function withRequiredValueRules<T extends z.ZodTypeAny>(schema: T, t: TFunction) {
  return schema.superRefine((values, ctx) => {
    const record = values as { category_id: number | null; cost: number | null; price: number | null }
    if (record.category_id === null) {
      ctx.addIssue({ code: 'custom', path: ['category_id'], message: t('products.form.categoryRequired') })
    }
    if (record.cost === null) {
      ctx.addIssue({ code: 'custom', path: ['cost'], message: t('products.form.costRequired') })
    }
    if (record.price === null) {
      ctx.addIssue({ code: 'custom', path: ['price'], message: t('products.form.priceRequired') })
    }
  })
}

/** `enum` is checked against `attribute.options`; every other type gets its native shape. */
function buildAttributeScalarSchema(attribute: EffectiveAttribute, t: TFunction): z.ZodTypeAny {
  switch (attribute.type) {
    case 'integer':
    case 'decimal':
      return z.number().nullable()
    case 'boolean':
      return z.boolean()
    case 'enum': {
      const values = new Set(attribute.options.map((option) => option.value))
      return z
        .string()
        .nullable()
        .superRefine((value, ctx) => {
          if (value !== null && !values.has(value)) {
            ctx.addIssue({ code: 'custom', message: t('customFields.validation.enumInvalid') })
          }
        })
    }
    case 'relation':
      return z.union([z.number(), z.array(z.number()), z.null()])
    // text/textarea + the string-backed scalars (date/datetime/time/email/url/color).
    default:
      return z.string().nullable()
  }
}

/** Builds the dynamic `attribute_values` shape, one key per PRODUCT-context effective attribute. */
function buildAttributeValuesSchema(attributes: EffectiveAttribute[], t: TFunction) {
  const shape: Record<string, z.ZodTypeAny> = {}
  for (const attribute of attributes) {
    shape[attribute.code] = buildAttributeScalarSchema(attribute, t)
  }

  const requiredCodes = attributes.filter((attribute) => attribute.is_required).map((attribute) => attribute.code)

  return z.object(shape).superRefine((values, ctx) => {
    for (const code of requiredCodes) {
      if (isEmptyCustomFieldValue((values as Record<string, unknown>)[code])) {
        ctx.addIssue({ code: 'custom', path: [code], message: t('customFields.validation.required') })
      }
    }
  })
}

/**
 * `buildAttributeValuesSchema` derives its shape from a runtime-keyed
 * `Record<string, ZodTypeAny>`, so Zod infers it as `Record<string, unknown>`
 * — re-typed to the real value domain (mirrors `asCustomFieldsField`) so it
 * embeds cleanly under the form's `attribute_values` key.
 */
type TypedAttributeValuesSchema = z.ZodType<Record<string, CustomFieldValue>, Record<string, CustomFieldValue>>

/**
 * `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec
 * 0021 AC-023); `productAttributes` drives `attribute_values` (spec 0061).
 */
export function buildCreateProductSchema(
  t: TFunction,
  customFieldsSchema: CustomFieldsSchema,
  productAttributes: EffectiveAttribute[],
) {
  return withRequiredValueRules(
    z.object({
      ...baseFields(t),
      custom_fields: asCustomFieldsField(customFieldsSchema),
      attribute_values: buildAttributeValuesSchema(productAttributes, t) as unknown as TypedAttributeValuesSchema,
    }),
    t,
  )
}

export function buildUpdateProductSchema(
  t: TFunction,
  customFieldsSchema: CustomFieldsSchema,
  productAttributes: EffectiveAttribute[],
) {
  return buildCreateProductSchema(t, customFieldsSchema, productAttributes)
}

export type CreateProductFormValues = z.infer<ReturnType<typeof buildCreateProductSchema>>
export type UpdateProductFormValues = CreateProductFormValues
