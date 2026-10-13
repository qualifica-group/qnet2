import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreatePurchaseRequestPriorityPayload,
  PurchaseRequestPriorityDetail,
  PurchaseRequestPriorityDetailWithPermissions,
  UpdatePurchaseRequestPriorityPayload,
} from '@/features/purchase-request-priorities/types'

/** Query key of a single RDA priority's detail, shared by the detail screen and its in-place saves. */
export function purchaseRequestPriorityDetailQueryKey(id: number) {
  return ['purchase-request-priorities', 'detail', id] as const
}

/**
 * Fetches a single RDA priority detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchPurchaseRequestPriority(id: number): Promise<PurchaseRequestPriorityDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<PurchaseRequestPriorityDetail, ResourcePermissions>
  >(`/purchase-request-priorities/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates an RDA priority. Returns the created resource from the envelope `data`. */
export async function createPurchaseRequestPriority(payload: CreatePurchaseRequestPriorityPayload): Promise<PurchaseRequestPriorityDetail> {
  const { data } = await apiClient.post<ApiResponse<PurchaseRequestPriorityDetail>>('/purchase-request-priorities', payload)
  return data.data
}

/**
 * Partially updates an RDA priority (PATCH). `system_key` and `sort_order` are
 * never keys of `UpdatePurchaseRequestPriorityPayload` (spec 0247 `data_contract`): the backend
 * rejects their mere presence with 422. Returns the saved record with the
 * actor's permissions, so the in-place detail re-syncs on it as is.
 */
export async function updatePurchaseRequestPriority(
  id: number,
  payload: UpdatePurchaseRequestPriorityPayload,
): Promise<PurchaseRequestPriorityDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<PurchaseRequestPriorityDetail, ResourcePermissions>
  >(`/purchase-request-priorities/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}

/**
 * Deletes an RDA priority. The backend answers 409 when the row is still used by
 * an RDA (spec 0247 D-3); both branches are surfaced by the
 * caller (`PurchaseRequestPrioritiesTable.runDelete`), not here.
 */
export async function deletePurchaseRequestPriority(id: number): Promise<void> {
  await apiClient.delete(`/purchase-request-priorities/${id}`)
}
