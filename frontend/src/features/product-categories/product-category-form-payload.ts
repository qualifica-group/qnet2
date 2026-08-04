import type {
  AttributeAssignmentInput,
  CreateProductCategoryPayload,
  ManagerLabels,
  ProductCategoryDetail,
  UpdateProductCategoryPayload,
} from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'
import { MANAGER_LABEL_POSITIONS } from '@/features/product-categories/product-category-schema'
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

/** Strips blank/whitespace-only rows and trims the rest, so the payload only ever carries valorized G.A. positions (spec 0080 AC-042). */
function buildManagerLabelsValue(labels: ManagerLabels): ManagerLabels {
  const result: ManagerLabels = {}
  for (const position of MANAGER_LABEL_POSITIONS) {
    const trimmed = (labels[String(position)] ?? '').trim()
    if (trimmed) {
      result[String(position)] = trimmed
    }
  }
  return result
}

/** Position-by-position comparison (spec 0080): object identity/key order never matters, only the resolved label per G.A. level. */
function sameManagerLabels(a: ManagerLabels, b: ManagerLabels): boolean {
  const aKeys = Object.keys(a)
  const bKeys = Object.keys(b)
  if (aKeys.length !== bKeys.length) {
    return false
  }
  return aKeys.every((key) => a[key] === b[key])
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
    // Only a ROOT category authors the quote flag; under a parent it is
    // inherited and the server resolves it (a divergent value is a 422).
    ...(values.parent_id === null ? { requires_quote: values.requires_quote } : {}),
    is_selectable: values.is_selectable,
    // Same rule for the management mode (spec 0077 D-2/INV-5): only a ROOT
    // authors it, a child inherits and a divergent value is a 422.
    ...(values.parent_id === null ? { management_mode: values.management_mode } : {}),
    manager_labels: buildManagerLabelsValue(values.manager_labels),
    inherits_manager_labels: values.inherits_manager_labels,
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

  // Sent only while the category stays (or becomes) a ROOT: on a child the
  // field is read-only and merely mirrors the root, so a diff there would be
  // an override attempt the server refuses. A reparent alone is enough — the
  // server re-aligns the moved subtree on its new root.
  if (values.parent_id === null && values.requires_quote !== original.requires_quote) {
    payload.requires_quote = values.requires_quote
  }

  // Per-node and never inherited (spec 0074 D-2): a plain diff, with no
  // parent-dependent guard around it.
  if (values.is_selectable !== original.is_selectable) {
    payload.is_selectable = values.is_selectable
  }

  // Same root-only guard as `requires_quote` (spec 0077 D-2): sent only while
  // the category stays (or becomes) a ROOT; on a child the field is
  // read-only and merely mirrors the root.
  if (values.parent_id === null && values.management_mode !== original.management_mode) {
    payload.management_mode = values.management_mode
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

  if (values.inherits_manager_labels !== original.inherits_manager_labels) {
    payload.inherits_manager_labels = values.inherits_manager_labels
  }
  const managerLabels = buildManagerLabelsValue(values.manager_labels)
  if (!sameManagerLabels(managerLabels, original.manager_labels)) {
    payload.manager_labels = managerLabels
  }

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}
