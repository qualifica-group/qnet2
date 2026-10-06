import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { SyncWorkOrderCostsPayload, WorkOrderCostOverview } from '@/features/work-order-costs/types'

/** `GET /work-orders/{id}/costs`: real costs, budget and comparison in one payload. */
export async function fetchWorkOrderCosts(workOrderId: number): Promise<WorkOrderCostOverview> {
  const { data } = await apiClient.get<ApiResponse<WorkOrderCostOverview>>(`/work-orders/${workOrderId}/costs`)
  return data.data
}

/** `PUT /work-orders/{id}/costs`: replaces the whole set, returns the refreshed overview. */
export async function syncWorkOrderCosts(
  workOrderId: number,
  payload: SyncWorkOrderCostsPayload,
): Promise<WorkOrderCostOverview> {
  const { data } = await apiClient.put<ApiResponse<WorkOrderCostOverview>>(
    `/work-orders/${workOrderId}/costs`,
    payload,
  )
  return data.data
}
