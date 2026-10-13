import type {
  PurchaseRequestLineStatusDetail,
  UpdatePurchaseRequestLineStatusPayload,
} from '@/features/purchase-request-line-statuses/types'
import type { PurchaseRequestLineStatusFormValues } from '@/features/purchase-request-line-statuses/purchase-request-line-status-schema'

/** Maps the form's `icon` (empty string = unset) onto the backend's nullable value. */
function iconValue(icon: string): string | null {
  return icon === '' ? null : icon
}

/**
 * Partial PATCH payload with only the fields that changed. `system_key` and
 * `sort_order` are never included: the backend 422s on their mere presence.
 */
export function buildUpdatePayload(
  values: PurchaseRequestLineStatusFormValues,
  original: PurchaseRequestLineStatusDetail,
): UpdatePurchaseRequestLineStatusPayload {
  const payload: UpdatePurchaseRequestLineStatusPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.color !== original.color) {
    payload.color = values.color
  }
  if (iconValue(values.icon) !== original.icon) {
    payload.icon = iconValue(values.icon)
  }

  return payload
}
