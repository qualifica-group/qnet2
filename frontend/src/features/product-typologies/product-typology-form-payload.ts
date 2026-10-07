import type {
  CreateProductTypologyPayload,
  ProductTypologyDetail,
  UpdateProductTypologyPayload,
} from '@/features/product-typologies/types'
import type { ProductTypologyFormValues } from '@/features/product-typologies/use-product-typology-form'

/** Builds the create payload: every field the form owns. */
export function buildCreatePayload(values: ProductTypologyFormValues): CreateProductTypologyPayload {
  return {
    name: values.name,
    code: values.code,
    description: values.description,
    supplier_commission_enabled: values.supplier_commission_enabled,
    supplier_commission_direction: normalizedDirection(values),
  }
}

/** Disabled commission always sends a null direction (spec 0202 D-7). */
function normalizedDirection(values: ProductTypologyFormValues) {
  return values.supplier_commission_enabled ? values.supplier_commission_direction : null
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original product typology. `code` is NEVER included
 * (D-2): it is immutable after create for every role, and the backend 422s
 * on its mere presence, even when the value is unchanged.
 */
export function buildUpdatePayload(
  values: ProductTypologyFormValues,
  original: ProductTypologyDetail,
): UpdateProductTypologyPayload {
  const payload: UpdateProductTypologyPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.supplier_commission_enabled !== original.supplier_commission_enabled) {
    payload.supplier_commission_enabled = values.supplier_commission_enabled
  }
  const direction = normalizedDirection(values)
  if (direction !== original.supplier_commission_direction) {
    payload.supplier_commission_direction = direction
  }

  return payload
}
