import type { CostTypeFormValues } from '@/features/cost-types/cost-type-schema'
import type { CostTypePayload } from '@/features/cost-types/types'

/** Create and update share one payload: PUT carries the whole resource. */
export function buildCostTypePayload(values: CostTypeFormValues): CostTypePayload {
  return {
    name: values.name.trim(),
    description: values.description === null || values.description.trim() === '' ? null : values.description,
    sort_order: values.sort_order,
    is_active: values.is_active,
  }
}
