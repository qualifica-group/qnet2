import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  GenerateOrdersPayload,
  GenerateOrdersResult,
  LineStatusChangePayload,
  LineStatusChangeResult,
  LineStatusLog,
  LineSupplierPayload,
  LineSupplierResult,
  PurchaseRequest,
  PurchaseRequestClosure,
  PurchaseRequestPayload,
  ApproverOptions,
} from '@/features/purchase-requests/types'

/** Centralized query keys of the purchase-requests feature. */
export const purchaseRequestKeys = {
  all: ['purchase-requests'] as const,
  detail: (id: number) => ['purchase-requests', 'detail', id] as const,
  closure: (id: number) => ['purchase-requests', 'closure', id] as const,
  lineLogs: (lineId: number) => ['purchase-requests', 'line-logs', lineId] as const,
  approverOptions: (requesterId: number | null, businessFunctionId: number | null) =>
    ['purchase-requests', 'approver-options', requesterId, businessFunctionId] as const,
}

export async function fetchPurchaseRequest(id: number): Promise<PurchaseRequest> {
  const { data } = await apiClient.get<ApiResponse<PurchaseRequest>>(`/purchase-requests/${id}`)
  return data.data
}

export async function createPurchaseRequest(payload: PurchaseRequestPayload): Promise<PurchaseRequest> {
  const { data } = await apiClient.post<ApiResponse<PurchaseRequest>>('/purchase-requests', payload)
  return data.data
}

/** Partial update (spec 0256 D-1): only the sent keys change; `lines`, when sent, is the whole set. */
export async function updatePurchaseRequest(
  id: number,
  payload: Partial<PurchaseRequestPayload>,
): Promise<PurchaseRequest> {
  const { data } = await apiClient.patch<ApiResponse<PurchaseRequest>>(`/purchase-requests/${id}`, payload)
  return data.data
}

/** 409 when a line is ordered or received (the caller shows the backend message). */
export async function deletePurchaseRequest(id: number): Promise<void> {
  await apiClient.delete(`/purchase-requests/${id}`)
}

/** Single or bulk transition; all-or-nothing server-side (403/409/422 leave every line untouched). */
export async function changeLinesStatus(payload: LineStatusChangePayload): Promise<LineStatusChangeResult> {
  const { data } = await apiClient.post<ApiResponse<LineStatusChangeResult>>(
    '/purchase-request-lines/status',
    payload,
  )
  return data.data
}

/** Bulk "Assign supplier" (spec 0209 D-4): one supplier for every line, all-or-nothing server-side. */
export async function assignLinesSupplier(payload: LineSupplierPayload): Promise<LineSupplierResult> {
  const { data } = await apiClient.post<ApiResponse<LineSupplierResult>>(
    '/purchase-request-lines/supplier',
    payload,
  )
  return data.data
}

/** Bulk "Generate ODA" (spec 0210 D-17): one draft ODA per supplier group, all-or-nothing server-side (403/409/422). */
export async function generatePurchaseOrders(payload: GenerateOrdersPayload): Promise<GenerateOrdersResult> {
  const { data } = await apiClient.post<ApiResponse<GenerateOrdersResult>>(
    '/purchase-request-lines/purchase-orders',
    payload,
  )
  return data.data
}

export async function fetchLineStatusLogs(lineId: number): Promise<LineStatusLog[]> {
  const { data } = await apiClient.get<ApiResponse<LineStatusLog[]>>(
    `/purchase-request-lines/${lineId}/status-logs`,
  )
  return data.data
}

export async function fetchClosure(id: number): Promise<PurchaseRequestClosure> {
  const { data } = await apiClient.get<ApiResponse<PurchaseRequestClosure>>(`/purchase-requests/${id}/closure`)
  return data.data
}

export async function closePurchaseRequest(id: number, reason?: string): Promise<PurchaseRequest> {
  const { data } = await apiClient.post<ApiResponse<PurchaseRequest>>(`/purchase-requests/${id}/close`, {
    reason,
  })
  return data.data
}

/** Resolves with the envelope message ("RDA sent to {name}"). */
export async function notifyPurchaseRequestManager(id: number): Promise<string> {
  const { data } = await apiClient.post<ApiResponse<null>>(`/purchase-requests/${id}/notify-manager`)
  return data.message
}

/** Allowed approvers for a requester (spec 0245 D-5): the requester's direct superiors, else the function manager. */
export async function fetchApproverOptions(
  requesterId: number,
  businessFunctionId: number | null,
): Promise<ApproverOptions> {
  const { data } = await apiClient.get<ApiResponse<ApproverOptions>>('/purchase-requests/approver-options', {
    params: { requester_id: requesterId, ...(businessFunctionId === null ? {} : { business_function_id: businessFunctionId }) },
  })
  return data.data
}
