import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  LineStatusChangePayload,
  LineStatusChangeResult,
  LineStatusLog,
  PurchaseRequest,
  PurchaseRequestClosure,
  PurchaseRequestPayload,
} from '@/features/purchase-requests/types'

/** Centralized query keys of the purchase-requests feature. */
export const purchaseRequestKeys = {
  all: ['purchase-requests'] as const,
  detail: (id: number) => ['purchase-requests', 'detail', id] as const,
  closure: (id: number) => ['purchase-requests', 'closure', id] as const,
  lineLogs: (lineId: number) => ['purchase-requests', 'line-logs', lineId] as const,
}

export async function fetchPurchaseRequest(id: number): Promise<PurchaseRequest> {
  const { data } = await apiClient.get<ApiResponse<PurchaseRequest>>(`/purchase-requests/${id}`)
  return data.data
}

export async function createPurchaseRequest(payload: PurchaseRequestPayload): Promise<PurchaseRequest> {
  const { data } = await apiClient.post<ApiResponse<PurchaseRequest>>('/purchase-requests', payload)
  return data.data
}

export async function updatePurchaseRequest(
  id: number,
  payload: PurchaseRequestPayload,
): Promise<PurchaseRequest> {
  const { data } = await apiClient.put<ApiResponse<PurchaseRequest>>(`/purchase-requests/${id}`, payload)
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
