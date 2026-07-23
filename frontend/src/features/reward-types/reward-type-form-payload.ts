import type {
  CreateRewardTypePayload,
  RewardTypeDetail,
  UpdateRewardTypePayload,
} from '@/features/reward-types/types'
import type { RewardTypeFormValues } from '@/features/reward-types/use-reward-type-form'

/**
 * Builds the create payload: `name` and `color`. Unlike the
 * `opportunity-statuses` template, `color` is never mapped to `null` — it is
 * required end to end (D-5), so the form value is sent verbatim.
 */
export function buildCreatePayload(values: RewardTypeFormValues): CreateRewardTypePayload {
  return {
    name: values.name,
    color: values.color,
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original reward type.
 */
export function buildUpdatePayload(
  values: RewardTypeFormValues,
  original: RewardTypeDetail,
): UpdateRewardTypePayload {
  const payload: UpdateRewardTypePayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.color !== original.color) {
    payload.color = values.color
  }

  return payload
}
