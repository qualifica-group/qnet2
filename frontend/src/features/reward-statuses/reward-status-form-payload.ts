import type {
  CreateRewardStatusPayload,
  RewardStatusDetail,
  UpdateRewardStatusPayload,
} from '@/features/reward-statuses/types'
import type { RewardStatusFormValues } from '@/features/reward-statuses/use-reward-status-form'

/**
 * Builds the create payload: `name`, `description`, `color` (never blanked,
 * D-4) and `is_active` (`sort_order`/`system_key` are server-managed, D-3/D-2).
 */
export function buildCreatePayload(values: RewardStatusFormValues): CreateRewardStatusPayload {
  return {
    name: values.name,
    description: values.description,
    color: values.color,
    is_active: values.is_active,
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original reward status.
 */
export function buildUpdatePayload(
  values: RewardStatusFormValues,
  original: RewardStatusDetail,
): UpdateRewardStatusPayload {
  const payload: UpdateRewardStatusPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.color !== original.color) {
    payload.color = values.color
  }
  if (values.is_active !== original.is_active) {
    payload.is_active = values.is_active
  }

  return payload
}
