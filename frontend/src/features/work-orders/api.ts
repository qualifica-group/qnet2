import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateWorkOrderPayload,
  UpdateWorkOrderPayload,
  WorkOrderDetail,
  WorkOrderDetailWithPermissions,
} from '@/features/work-orders/types'

/** Table/module domain key of this module, shared by every adapter (mirrors `QUOTES_DOMAIN`). */
export const WORK_ORDERS_DOMAIN = 'work-orders'

/**
 * Fetches a single work order detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchWorkOrder(id: number): Promise<WorkOrderDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<WorkOrderDetail, ResourcePermissions>
  >(`/work-orders/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/**
 * The next sequential code (`COM-0001`...) suggested for the create form's
 * `code` auto-fill (D-1, mirrors `fetchQuoteNextCode`). A preview only: it
 * does not consume the sequence.
 */
export async function fetchWorkOrderNextCode(): Promise<string> {
  const { data } = await apiClient.get<ApiResponse<{ code: string }>>('/work-orders/next-code')
  return data.data.code
}

/** Creates a work order. `code` falls back to server-side generation when omitted/empty. */
export async function createWorkOrder(payload: CreateWorkOrderPayload): Promise<WorkOrderDetail> {
  const { data } = await apiClient.post<ApiResponse<WorkOrderDetail>>('/work-orders', payload)
  return data.data
}

/**
 * Partially updates a work order (PATCH). `code`/`quote_id` are never keys of
 * `UpdateWorkOrderPayload` (D-1/D-5): the backend rejects their mere presence
 * with 422 regardless of role.
 */
export async function updateWorkOrder(
  id: number,
  payload: UpdateWorkOrderPayload,
): Promise<WorkOrderDetail> {
  const { data } = await apiClient.patch<ApiResponse<WorkOrderDetail>>(`/work-orders/${id}`, payload)
  return data.data
}

/** Deletes a work order. Backend responds 204 with no body; its pivot rows cascade (D-11). */
export async function deleteWorkOrder(id: number): Promise<void> {
  await apiClient.delete(`/work-orders/${id}`)
}
