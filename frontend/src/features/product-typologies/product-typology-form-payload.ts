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
  }
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

  return payload
}
