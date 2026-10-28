import { isEmptyCustomFieldValue, isEqualCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type {
  CreateProductPayload,
  ProductDetail,
  ProductUsage,
  UpdateProductPayload,
} from '@/features/products/types'
import { rowsFromOptions, sameSupplierRows, toSuppliersPayload } from '@/features/products/product-supplier-rows'
import type { ProductFormValues } from '@/features/products/use-product-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'
import { submittedCode } from '@/lib/sequential-code'

type AttributeValues = Record<string, CustomFieldValue>

/** A product with no explicit choice is Sellable only (spec 0142, D-3), mirroring the server default. */
export const DEFAULT_PRODUCT_USAGES: ProductUsage[] = ['SALE']

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
 * server. `code` is included only when typed by the user (`submittedCode`):
 * blank or still the `next-code` suggestion, it is omitted and the server
 * assigns the next free sequential code at save time (spec 0065).
 */
export function buildCreatePayload(
  values: ProductFormValues,
  productAttributeCodes: string[],
  suggestedCode?: string,
): CreateProductPayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  const attributeValues = buildAttributeValuesCreate(values.attribute_values, productAttributeCodes)
  return {
    ...submittedCode(values.code, suggestedCode),
    name: values.name,
    description: values.description,
    // Every usage has both amounts (rev.3 D-20); the standard ones are hidden (so null) when suppliers carry them.
    cost: values.cost ?? 0,
    price: values.price ?? 0,
    category_id: values.category_id as number,
    product_type: values.product_type,
    usages: values.usages,
    vat_rate_id: values.vat_rate_id,
    unit_of_measure_id: values.unit_of_measure_id,
    product_typology_id: values.product_typology_id,
    ...(values.cost_type_id !== null ? { cost_type_id: values.cost_type_id } : {}),
    ...(values.suppliers.length > 0 ? { suppliers: toSuppliersPayload(values.suppliers) } : {}),
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
    payload.cost = values.cost ?? 0
  }
  if (values.price !== normalizeDecimal(original.price)) {
    payload.price = values.price ?? 0
  }
  if (values.category_id !== original.category_id) {
    payload.category_id = values.category_id as number
  }
  if (values.product_type !== original.product_type) {
    payload.product_type = values.product_type
  }
  if (!sameUsages(values.usages, original.usages ?? DEFAULT_PRODUCT_USAGES)) {
    payload.usages = values.usages
  }
  if (values.vat_rate_id !== original.vat_rate_id) {
    payload.vat_rate_id = values.vat_rate_id
  }
  // Spec 0320: the list is replaced as a whole, so it is sent only when it changed.
  if (!sameSupplierRows(values.suppliers, rowsFromOptions(original.suppliers))) {
    payload.suppliers = toSuppliersPayload(values.suppliers)
  }
  if (values.unit_of_measure_id !== original.unit_of_measure_id) {
    payload.unit_of_measure_id = values.unit_of_measure_id
  }
  if (values.product_typology_id !== original.product_typology_id) {
    payload.product_typology_id = values.product_typology_id
  }
  if (values.cost_type_id !== (original.cost_type_id ?? null)) {
    payload.cost_type_id = values.cost_type_id
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

/** Order-insensitive: the usages are a set, the checkbox order is not a change. */
function sameUsages(next: ProductUsage[], original: ProductUsage[]): boolean {
  return next.length === original.length && next.every((usage) => original.includes(usage))
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
