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

/** `ProductCategory::MANAGER_LABEL_MAX_LENGTH` (spec 0080): a manager label's max length. */
export const MANAGER_LABEL_MAX_LENGTH = 60

/**
 * `ProductCategory::MANAGER_LABEL_MAX_POSITION` (spec 0080 amendment A1): the
 * safety ceiling on G.A. levels, aligned with the backend
 * `ValidatesManagerSlots::MAX_MANAGER_SLOTS`. The single source of truth for
 * every cap check in the manager-labels section — never a bare `12` (or `4`)
 * sprinkled elsewhere.
 */
export const MANAGER_LABEL_MAX_POSITION = 12

/**
 * How many rows the section opens with when the category has fewer own
 * positions than this (spec 0080 A1 D-frontend: "un minimo ragionevole cosi'
 * la sezione non appare vuota"). Padded with the smallest free positions —
 * this is the same baseline the fixed 4-row layout used to always show.
 */
export const MANAGER_LABEL_MIN_ROWS = 4

/**
 * The smallest G.A. position (1..`MANAGER_LABEL_MAX_POSITION`) not already in
 * `positions` — what "Add level" appends next. `null` at the ceiling (spec
 * 0080 A1 AC-055).
 */
export function nextFreeManagerLabelPosition(positions: number[]): number | null {
  const used = new Set(positions)
  for (let position = 1; position <= MANAGER_LABEL_MAX_POSITION; position += 1) {
    if (!used.has(position)) {
      return position
    }
  }
  return null
}

/** Pads `positions` with the smallest free ones until it reaches `minimum` rows (or the ceiling is hit) — never drops what is already there. */
export function padManagerLabelPositions(positions: number[], minimum: number): number[] {
  const rows = [...new Set(positions)]
  while (rows.length < minimum) {
    const next = nextFreeManagerLabelPosition(rows)
    if (next === null) {
      break
    }
    rows.push(next)
  }
  return rows.sort((a, b) => a - b)
}

function baseFields(t: TFunction) {
  return {
    name: z
      .string()
      .min(1, t('productCategories.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('productCategories.form.nameMax')),
    parent_id: z.number().nullable(),
    inherits_product_attributes: z.boolean(),
    inherits_quote_attributes: z.boolean(),
    inherits_work_order_attributes: z.boolean(),
    description: z.string().nullable(),
    business_function_id: z.number().nullable(),
    requires_quote: z.boolean(),
    is_selectable: z.boolean(),
    management_mode: z.enum(['single', 'multiple']),
    single_quote_per_opportunity: z.boolean(),
    generates_contract: z.boolean(),
    attributes: z.array(
      z.object({
        attribute_id: z.number(),
        context: z.enum(['product', 'quote', 'work_order']),
        is_required: z.boolean(),
        sort_order: z.number().int(),
      }),
    ),
    manager_labels: z.record(
      z.string(),
      z.string().max(MANAGER_LABEL_MAX_LENGTH, t('productCategories.form.managerLabelMax')),
    ),
    inherits_manager_labels: z.boolean(),
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
