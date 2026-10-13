import { apiClient } from '@/api/client'
import type { ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import { forSelectKeys } from '@/features/for-select/query-keys'
import {
  PURCHASE_ORDER_STATUSES_RESOURCE,
  type PurchaseOrderStatusDetail,
  type PurchaseOrderStatusDetailWithPermissions,
  type UpdatePurchaseOrderStatusPayload,
} from '@/features/purchase-order-statuses/types'

/**
 * Query key of the presentation catalog (all rows, by `sort_order`). It lives
 * under the for-select prefix of the resource so the reorder sheet, which
 * invalidates that prefix on success, refreshes every consumer of the catalog.
 */
export const PURCHASE_ORDER_STATUS_CATALOG_KEY = [
  ...forSelectKeys.resource(PURCHASE_ORDER_STATUSES_RESOURCE),
  'catalog',
] as const

/** Query key of a single status detail, shared by the detail screen and its in-place saves. */
export function purchaseOrderStatusDetailQueryKey(id: number) {
  return [PURCHASE_ORDER_STATUSES_RESOURCE, 'detail', id] as const
}

/** Fetches a single ODA status together with the actor's authorization metadata. */
export async function fetchPurchaseOrderStatus(id: number): Promise<PurchaseOrderStatusDetailWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<PurchaseOrderStatusDetail, ResourcePermissions>>(
    `/${PURCHASE_ORDER_STATUSES_RESOURCE}/${id}`,
  )
  return { ...data.data, permissions: data.permissions }
}

/** Partially updates an ODA status (PATCH): name, color and icon only. */
export async function updatePurchaseOrderStatus(
  id: number,
  payload: UpdatePurchaseOrderStatusPayload,
): Promise<PurchaseOrderStatusDetailWithPermissions> {
  const { data } = await apiClient.patch<ApiResponseWithPermissions<PurchaseOrderStatusDetail, ResourcePermissions>>(
    `/${PURCHASE_ORDER_STATUSES_RESOURCE}/${id}`,
    payload,
  )
  return { ...data.data, permissions: data.permissions }
}
