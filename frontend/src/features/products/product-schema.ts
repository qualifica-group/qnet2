import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  asCustomFieldsField,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'
import { isEmptyCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { hiddenAttributeCodes } from '@/features/attributes/attribute-section-visibility'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import { buildAttributeEnumSchema } from '@/features/request-management/attribute-values-schema'
import type { ProductSupplierRow } from '@/features/products/product-supplier-rows'
import type { ProductType, ProductUsage } from '@/features/products/types'

/**
 * Zod schema for the product create/edit form's GENERIC fields, built as a
 * factory so validation messages are localized via the i18n `t` function.
 * `attribute_values` (spec 0061) is additive: one entry per the selected
 * category's PRODUCT-context effective attribute, mirroring
 * `attribute-values-schema.ts`'s `buildAttributeValuesSchema` (kept independent
 * over the product's own effective-attribute shape; the per-option `enum` rule
 * is the shared `buildAttributeEnumSchema`).
 */

/** Backend `name` column limit (`max:191`). */
const NAME_MAX_LENGTH = 191

/** Backend `code` column limit (`string(32)`). */
const CODE_MAX_LENGTH = 32

function baseFields(t: TFunction) {
  return {
    // Manual code (spec 0065, mirrors the project's `code`): trimmed,
    // required (the create form auto-fills the next sequential suggestion,
    // editable), max 32. Read-only in edit (enforced by the field-permission
    // ceiling); the server still generates one as a fallback when absent.
    code: z
      .string()
      .trim()
      .min(1, t('products.form.codeRequired'))
      .max(CODE_MAX_LENGTH, t('products.form.codeMax')),
    name: z
      .string()
      .min(1, t('products.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('products.form.nameMax')),
    description: z.string().nullable(),
    // cost/price/category_id are held nullable so the controlled inputs can
    // represent "empty"; the required-value superRefine below rejects a null
    // at submit (cost/price only without suppliers), mirroring the backend.
    cost: z.number().nonnegative(t('products.form.costInvalid')).nullable(),
    price: z.number().nonnegative(t('products.form.priceInvalid')).nullable(),
    category_id: z.number().nullable(),
    product_type: z.enum(['SERVICE', 'PHYSICAL']),
    // Spec 0142: the independent Sellable / Usable-as-cost set, at least one.
    usages: z.array(z.enum(['SALE', 'COST', 'INTERNAL'])).min(1, t('products.form.usagesRequired')),
    vat_rate_id: z.number().nullable(),
    // Spec 0320: the product's suppliers with their own cost; completeness,
    // duplicates and the single default are checked in `withRequiredValueRules`.
    suppliers: z.array(
      z.object({
        supplier_id: z.number().nullable(),
        name: z.string(),
        price: z.number().nullable(),
        cost: z.number().nullable(),
        is_default: z.boolean(),
      }),
    ),
    // `null`/unset resolves server-side to the default unit (spec 0088, D-4).
    unit_of_measure_id: z.number().nullable(),
    product_typology_id: z.number().nullable(),
    // Spec 0251: optional default cost type of the product.
    cost_type_id: z.number().nullable(),
  }
}

function addAmountIssue(ctx: z.RefinementCtx, index: number, field: 'price' | 'cost', value: number | null, t: TFunction) {
  const message = value === null ? t(`products.form.suppliers.${field}Required`) : value < 0 ? t(`products.form.${field}Invalid`) : null
  if (message) {
    ctx.addIssue({ code: 'custom', path: ['suppliers', index, field], message })
  }
}

/** Each supplier row complete, no supplier twice, and with 2+ rows exactly one default (spec 0320 D-1/D-2). */
function addSupplierIssues(rows: ProductSupplierRow[], ctx: z.RefinementCtx, t: TFunction) {
  const seen = new Set<number>()
  rows.forEach((row, index) => {
    if (row.supplier_id === null) {
      ctx.addIssue({ code: 'custom', path: ['suppliers', index, 'supplier_id'], message: t('products.form.suppliers.supplierRequired') })
    } else if (seen.has(row.supplier_id)) {
      ctx.addIssue({ code: 'custom', path: ['suppliers', index, 'supplier_id'], message: t('products.form.suppliers.duplicate') })
    } else {
      seen.add(row.supplier_id)
    }
    // Rev.3 D-20: every supplier carries both amounts, whatever the usages.
    addAmountIssue(ctx, index, 'price', row.price, t)
    addAmountIssue(ctx, index, 'cost', row.cost, t)
  })
  if (rows.length > 1 && rows.filter((row) => row.is_default).length !== 1) {
    ctx.addIssue({ code: 'custom', path: ['suppliers'], message: t('products.form.suppliers.defaultRequired') })
  }
}

function withRequiredValueRules<T extends z.ZodTypeAny>(schema: T, t: TFunction) {
  return schema.superRefine((values, ctx) => {
    const record = values as {
      category_id: number | null
      cost: number | null
      price: number | null
      usages: ProductUsage[]
      product_type: ProductType
      suppliers: ProductSupplierRow[]
    }
    // Spec 0307 D-2: internal use (stock) only makes sense on a physical good.
    if (record.usages.includes('INTERNAL') && record.product_type !== 'PHYSICAL') {
      ctx.addIssue({ code: 'custom', path: ['usages'], message: t('products.form.usagesInternalNeedsPhysical') })
    }
    if (record.category_id === null) {
      ctx.addIssue({ code: 'custom', path: ['category_id'], message: t('products.form.categoryRequired') })
    }
    addSupplierIssues(record.suppliers, ctx, t)
    // Rev.3 D-20: without suppliers the standard cost and price are required, for every usage.
    if (record.suppliers.length === 0 && record.cost === null) {
      ctx.addIssue({ code: 'custom', path: ['cost'], message: t('products.form.costRequired') })
    }
    if (record.suppliers.length === 0 && record.price === null) {
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
    case 'enum':
      return buildAttributeEnumSchema(attribute, t('customFields.validation.enumInvalid'))
    case 'relation':
      return z.union([z.number(), z.array(z.number()), z.null()])
    // text/textarea + the string-backed scalars (date/datetime/time/email/url/color).
    default:
      return z.string().nullable()
  }
}

/** Builds the dynamic `attribute_values` shape, one key per PRODUCT-context effective attribute. */
function buildAttributeValuesSchema(attributes: EffectiveAttribute[], t: TFunction, layout: LayoutBlob | null) {
  const shape: Record<string, z.ZodTypeAny> = {}
  for (const attribute of attributes) {
    shape[attribute.code] = buildAttributeScalarSchema(attribute, t)
  }

  const requiredCodes = attributes.filter((attribute) => attribute.is_required).map((attribute) => attribute.code)

  return z.object(shape).superRefine((values, ctx) => {
    // A required attribute in a hidden conditional section does not apply (spec 0288 D-4).
    const hidden = hiddenAttributeCodes(layout, values)
    for (const code of requiredCodes) {
      if (!hidden.has(code) && isEmptyCustomFieldValue((values as Record<string, unknown>)[code])) {
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
  layout: LayoutBlob | null = null,
) {
  return withRequiredValueRules(
    z.object({
      ...baseFields(t),
      custom_fields: asCustomFieldsField(customFieldsSchema),
      attribute_values: buildAttributeValuesSchema(productAttributes, t, layout) as unknown as TypedAttributeValuesSchema,
    }),
    t,
  )
}

export function buildUpdateProductSchema(
  t: TFunction,
  customFieldsSchema: CustomFieldsSchema,
  productAttributes: EffectiveAttribute[],
  layout: LayoutBlob | null = null,
) {
  return buildCreateProductSchema(t, customFieldsSchema, productAttributes, layout)
}

export type CreateProductFormValues = z.infer<ReturnType<typeof buildCreateProductSchema>>
export type UpdateProductFormValues = CreateProductFormValues
