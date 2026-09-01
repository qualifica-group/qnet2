import type {
  CreateUnitOfMeasurePayload,
  UnitOfMeasureDetail,
  UpdateUnitOfMeasurePayload,
} from '@/features/units-of-measure/types'
import type { UnitOfMeasureFormValues } from '@/features/units-of-measure/use-unit-of-measure-form'

/** Builds the create payload: every field the form owns. */
export function buildCreatePayload(values: UnitOfMeasureFormValues): CreateUnitOfMeasurePayload {
  return {
    name: values.name,
    symbol: values.symbol,
    code: values.code,
    description: values.description,
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original unit of measure. `code` is NEVER included
 * (D-1): it is immutable after create for every role, and the backend 422s
 * on its mere presence, even when the value is unchanged.
 */
export function buildUpdatePayload(
  values: UnitOfMeasureFormValues,
  original: UnitOfMeasureDetail,
): UpdateUnitOfMeasurePayload {
  const payload: UpdateUnitOfMeasurePayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.symbol !== original.symbol) {
    payload.symbol = values.symbol
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }

  return payload
}
