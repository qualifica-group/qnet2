import type { CostTypeFormValues } from '@/features/cost-types/cost-type-schema'
import type { CostTypeDetail, CostTypePayload, UpdateCostTypePayload } from '@/features/cost-types/types'

/** A blank description is sent as `null`. */
function descriptionValue(description: string | null): string | null {
  return description === null || description.trim() === '' ? null : description
}

/** Builds the create payload: every field the form owns (never `sort_order`). */
export function buildCreatePayload(values: CostTypeFormValues): CostTypePayload {
  return {
    name: values.name.trim(),
    description: descriptionValue(values.description),
    nature: values.nature,
    color: values.color,
    is_active: values.is_active,
  }
}

/** Builds a partial PATCH payload with only the fields that changed from the saved cost type. */
export function buildUpdatePayload(values: CostTypeFormValues, original: CostTypeDetail): UpdateCostTypePayload {
  const payload: UpdateCostTypePayload = {}

  if (values.name.trim() !== original.name) {
    payload.name = values.name.trim()
  }
  if (descriptionValue(values.description) !== original.description) {
    payload.description = descriptionValue(values.description)
  }
  if (values.nature !== original.nature) {
    payload.nature = values.nature
  }
  if (values.color !== original.color) {
    payload.color = values.color
  }
  if (values.is_active !== original.is_active) {
    payload.is_active = values.is_active
  }

  return payload
}
