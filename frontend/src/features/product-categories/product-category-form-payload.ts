import type {
  AttributeAssignmentInput,
  CreateProductCategoryPayload,
  ProductCategoryDetail,
  UpdateProductCategoryPayload,
} from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

function sameAssignments(a: AttributeAssignmentInput[], b: AttributeAssignmentInput[]): boolean {
  if (a.length !== b.length) {
    return false
  }
  const key = (assignment: AttributeAssignmentInput) =>
    `${assignment.attribute_id}:${assignment.context}:${assignment.is_required ?? false}:${assignment.sort_order ?? 0}`
  const bKeys = new Set(b.map(key))
  return a.every((assignment) => bKeys.has(key(assignment)))
}

/** Builds the create payload: generic fields + the own attribute assignments. */
export function buildCreatePayload(
  values: ProductCategoryFormValues,
): CreateProductCategoryPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    name: values.name,
    parent_id: values.parent_id,
    inherits_product_attributes: values.inherits_product_attributes,
    inherits_opportunity_attributes: values.inherits_opportunity_attributes,
    description: values.description,
    attributes: values.attributes,
    business_function_id: values.business_function_id,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original category (spec 0017 AC-010). `attributes` is a full-replace sync:
 * sent whenever the assignment set differs (by attribute/is_required/sort_order).
 */
export function buildUpdatePayload(
  values: ProductCategoryFormValues,
  original: ProductCategoryDetail,
): UpdateProductCategoryPayload {
  const payload: UpdateProductCategoryPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.parent_id !== original.parent_id) {
    payload.parent_id = values.parent_id
  }
  // The two inheritance barriers are independent: each is diffed and sent on its own.
  if (values.inherits_product_attributes !== original.inherits_product_attributes) {
    payload.inherits_product_attributes = values.inherits_product_attributes
  }
  if (values.inherits_opportunity_attributes !== original.inherits_opportunity_attributes) {
    payload.inherits_opportunity_attributes = values.inherits_opportunity_attributes
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  // Never diverges from `original.business_function_id` while the field is
  // inherited (disabled — no user interaction possible), so this diff alone
  // satisfies "never send business_function_id when inherited" (spec AC-015).
  if (values.business_function_id !== original.business_function_id) {
    payload.business_function_id = values.business_function_id
  }

  const originalAssignments: AttributeAssignmentInput[] = original.attributes.map((a) => ({
    attribute_id: a.attribute_id,
    context: a.context,
    is_required: a.is_required,
    sort_order: a.sort_order,
  }))
  if (!sameAssignments(values.attributes, originalAssignments)) {
    payload.attributes = values.attributes
  }

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}
