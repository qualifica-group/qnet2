import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateWorkOrderPaymentStatusPayload,
  WorkOrderPaymentStatusDetail,
  WorkOrderPaymentStatusDetailWithPermissions,
  UpdateWorkOrderPaymentStatusPayload,
} from '@/features/work-order-payment-statuses/types'

/**
 * Fetches a single work order payment status detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchWorkOrderPaymentStatus(id: number): Promise<WorkOrderPaymentStatusDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<WorkOrderPaymentStatusDetail, ResourcePermissions>
  >(`/work-order-payment-statuses/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a work order payment status. Returns the created resource from the envelope `data`. */
export async function createWorkOrderPaymentStatus(
  payload: CreateWorkOrderPaymentStatusPayload,
): Promise<WorkOrderPaymentStatusDetail> {
  const { data } = await apiClient.post<ApiResponse<WorkOrderPaymentStatusDetail>>(
    '/work-order-payment-statuses',
    payload,
  )
  return data.data
}

/** Partially updates a work order payment status (PATCH). Returns the updated resource. */
export async function updateWorkOrderPaymentStatus(
  id: number,
  payload: UpdateWorkOrderPaymentStatusPayload,
): Promise<WorkOrderPaymentStatusDetail> {
  const { data } = await apiClient.patch<ApiResponse<WorkOrderPaymentStatusDetail>>(
    `/work-order-payment-statuses/${id}`,
    payload,
  )
  return data.data
}

/**
 * Deletes a work order payment status. Backend responds 204 with no body, or 409 when
 * the status is still referenced by a work order line (BR-4) — the caller surfaces
 * the backend's exact `message` for that case.
 */
export async function deleteWorkOrderPaymentStatus(id: number): Promise<void> {
  await apiClient.delete(`/work-order-payment-statuses/${id}`)
}
