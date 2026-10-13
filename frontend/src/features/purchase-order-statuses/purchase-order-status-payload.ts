import type { PurchaseOrderStatusFormValues } from '@/features/purchase-order-statuses/purchase-order-status-schema'
import type {
  PurchaseOrderStatusDetail,
  UpdatePurchaseOrderStatusPayload,
} from '@/features/purchase-order-statuses/types'

/** Maps the form's `icon` (empty string = unset) onto the nullable value of the backend. */
function iconValue(icon: string): string | null {
  return icon === '' ? null : icon
}

/** Builds a partial PATCH carrying only what changed; `system_key`/`sort_order` are never included (422). */
export function buildUpdatePayload(
  values: PurchaseOrderStatusFormValues,
  original: PurchaseOrderStatusDetail,
): UpdatePurchaseOrderStatusPayload {
  const payload: UpdatePurchaseOrderStatusPayload = {}

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
