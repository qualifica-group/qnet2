import { apiClient } from '@/api/client'
import type { ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  PurchaseRequestLineStatusDetail,
  PurchaseRequestLineStatusDetailWithPermissions,
  UpdatePurchaseRequestLineStatusPayload,
} from '@/features/purchase-request-line-statuses/types'

/** Query key of a single line status detail, shared by the detail screen and its in-place saves. */
export function purchaseRequestLineStatusDetailQueryKey(id: number) {
  return ['purchase-request-line-statuses', 'detail', id] as const
}

/** Fetches a line status together with the actor's authorization metadata. */
export async function fetchPurchaseRequestLineStatus(id: number): Promise<PurchaseRequestLineStatusDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<PurchaseRequestLineStatusDetail, ResourcePermissions>
  >(`/purchase-request-line-statuses/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/**
 * Partially updates a line status (PATCH). `system_key` and `sort_order` are
 * never keys of the payload type: the backend rejects their presence with 422.
 */
export async function updatePurchaseRequestLineStatus(
  id: number,
  payload: UpdatePurchaseRequestLineStatusPayload,
): Promise<PurchaseRequestLineStatusDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<PurchaseRequestLineStatusDetail, ResourcePermissions>
  >(`/purchase-request-line-statuses/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}
