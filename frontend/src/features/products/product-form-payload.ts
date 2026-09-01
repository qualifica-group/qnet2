import { isEmptyCustomFieldValue, isEqualCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type {
  CreateProductPayload,
  ProductDetail,
  UpdateProductPayload,
} from '@/features/products/types'
import type { ProductFormValues } from '@/features/products/use-product-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'

type AttributeValues = Record<string, CustomFieldValue>

/** Additive (spec 0061): every VALUED code among the CURRENT category's PRODUCT attributes, empty/unset ones omitted. */
function buildAttributeValuesCreate(values: AttributeValues, codes: string[]): AttributeValues {
  const payload: AttributeValues = {}
  for (const code of codes) {
    const value = values[code] ?? null
    if (!isEmptyCustomFieldValue(value)) {
      payload[code] = value
    }
  }
  return payload
}

/** Sparse PATCH: only codes whose value actually changed from the loaded product. */
function buildAttributeValuesUpdate(values: AttributeValues, original: AttributeValues, codes: string[]): AttributeValues {
  const payload: AttributeValues = {}
  for (const code of codes) {
    const value = values[code] ?? null
    if (!isEqualCustomFieldValue(value, original[code] ?? null)) {
      payload[code] = value
    }
  }
  return payload
}

/**
 * Builds the create payload: generic fields + valued custom fields + valued
 * attribute values. `productAttributeCodes` is the selected category's
 * CURRENT PRODUCT-context attribute set — the only codes ever sent, so a
 * stale code left over from a since-abandoned category never reaches the
 * server. `code` is included only when set (trimmed, non-empty) — an
 * empty/absent value falls back to server-side sequential generation (spec
 * 0065, mirrors the project's `code`).
 */
export function buildCreatePayload(
  values: ProductFormValues,
  productAttributeCodes: string[],
): CreateProductPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  const attributeValues = buildAttributeValuesCreate(values.attribute_values, productAttributeCodes)
  const code = values.code.trim()
  return {
    ...(code ? { code } : {}),
    name: values.name,
    description: values.description,
    // cost/price/category_id are validated non-null by the schema's
    // required-value superRefine before submit.
    cost: values.cost as number,
    price: values.price as number,
    category_id: values.category_id as number,
    product_type: values.product_type,
    vat_rate_id: values.vat_rate_id,
    supplier_id: values.supplier_id,
    unit_of_measure_id: values.unit_of_measure_id,
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
    ...(Object.keys(attributeValues).length > 0 ? { attribute_values: attributeValues } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original product (spec 0017 AC-024), plus any changed attribute value
 * (spec 0061). `code` is never sent: it is immutable after create (spec
 * 0065, mirrors the project's `code`).
 */
export function buildUpdatePayload(
  values: ProductFormValues,
  original: ProductDetail,
  productAttributeCodes: string[],
): UpdateProductPayload {
  const payload: UpdateProductPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.cost !== normalizeDecimal(original.cost)) {
    // See buildCreatePayload: validated non-null by the schema's
    // required-value superRefine before submit.
    payload.cost = values.cost as number
  }
  if (values.price !== normalizeDecimal(original.price)) {
    payload.price = values.price as number
  }
  if (values.category_id !== original.category_id) {
    payload.category_id = values.category_id as number
  }
  if (values.product_type !== original.product_type) {
    payload.product_type = values.product_type
  }
  if (values.vat_rate_id !== original.vat_rate_id) {
    payload.vat_rate_id = values.vat_rate_id
  }
  if (values.supplier_id !== original.supplier_id) {
    payload.supplier_id = values.supplier_id
  }
  if (values.unit_of_measure_id !== original.unit_of_measure_id) {
    payload.unit_of_measure_id = values.unit_of_measure_id
  }

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  const attributeValues = buildAttributeValuesUpdate(
    values.attribute_values,
    original.attribute_values ?? {},
    productAttributeCodes,
  )
  if (Object.keys(attributeValues).length > 0) {
    payload.attribute_values = attributeValues
  }

  return payload
}

/**
 * Normalizes a money field (`decimal:2` server-side, so serialized as the
 * string `"12.00"`) to the number the form and its zod schema work with.
 * Twin of `normalizeDecimal` in the opportunities feature.
 */
export function normalizeDecimal(value: string | number | null): number | null {
  if (value === null) {
    return null
  }
  return typeof value === 'number' ? value : Number(value)
}
