import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'

/**
 * Zod schema for the product-category create/edit form, built as a factory so
 * validation messages are localized via the i18n `t` function. The shape
 * mirrors the frozen backend contract (spec 0017) 1:1; the anti-cycle
 * `parent_id` rule is enforced server-side (the picker also excludes the
 * category's own subtree client-side as a UX affordance, not a validity gate).
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('productCategories.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('productCategories.form.nameMax')),
    parent_id: z.number().nullable(),
    inherits_product_attributes: z.boolean(),
    inherits_opportunity_attributes: z.boolean(),
    description: z.string().nullable(),
    business_function_id: z.number().nullable(),
    requires_quote: z.boolean(),
    is_selectable: z.boolean(),
    management_mode: z.enum(['single', 'multiple']),
    attributes: z.array(
      z.object({
        attribute_id: z.number(),
        context: z.enum(['product', 'opportunity']),
        is_required: z.boolean(),
        sort_order: z.number().int(),
      }),
    ),
  }
}

/** `customFieldsSchema` is the toolbox-built schema for `custom_fields` (spec 0021 AC-023). */
export function buildCreateProductCategorySchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

export function buildUpdateProductCategorySchema(t: TFunction, customFieldsSchema: CustomFieldsSchema) {
  return z.object({ ...baseFields(t), custom_fields: asCustomFieldsField(customFieldsSchema) })
}

export type CreateProductCategoryFormValues = z.infer<
  ReturnType<typeof buildCreateProductCategorySchema>
>
export type UpdateProductCategoryFormValues = CreateProductCategoryFormValues
