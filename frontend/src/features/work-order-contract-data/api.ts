import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  BulkUpdateContractLinesPayload,
  ContractDataLine,
  UpdateContractLinePayload,
  WorkOrderContractData,
} from '@/features/work-order-contract-data/types'

/** `GET /work-orders/{id}/contract-data`: lines, totals and the commission visibility flag. */
export async function fetchWorkOrderContractData(workOrderId: number): Promise<WorkOrderContractData> {
  const { data } = await apiClient.get<ApiResponse<WorkOrderContractData>>(
    `/work-orders/${workOrderId}/contract-data`,
  )
  return data.data
}

/** `PATCH /work-orders/{id}/contract-data/lines/{quoteLineId}`: returns the single refreshed line. */
export async function updateContractDataLine(
  workOrderId: number,
  quoteLineId: number,
  payload: UpdateContractLinePayload,
): Promise<ContractDataLine> {
  const { data } = await apiClient.patch<ApiResponse<ContractDataLine>>(
    `/work-orders/${workOrderId}/contract-data/lines/${quoteLineId}`,
    payload,
  )
  return data.data
}

/** `PATCH /work-orders/{id}/contract-data/lines` (spec 0333): returns the whole refreshed contract data. */
export async function bulkUpdateContractDataLines(
  workOrderId: number,
  payload: BulkUpdateContractLinesPayload,
): Promise<WorkOrderContractData> {
  const { data } = await apiClient.patch<ApiResponse<WorkOrderContractData>>(
    `/work-orders/${workOrderId}/contract-data/lines`,
    payload,
  )
  return data.data
}
