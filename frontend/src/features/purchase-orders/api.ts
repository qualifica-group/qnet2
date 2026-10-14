import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { filenameFromContentDisposition, normalizeBlobError, saveBlob } from '@/lib/download'
import {
  PURCHASE_ORDERS_DOMAIN,
  type CreatedPurchaseOrder,
  type CreatedReceipt,
  type MergeCandidate,
  type MergeLog,
  type MergeResult,
  type PurchaseOrder,
  type PurchaseOrderPayload,
  type PurchaseOrderReceipt,
  type PurchaseOrderReceiptPayload,
  type PurchaseOrderStatus,
} from '@/features/purchase-orders/types'

const ENDPOINT = `/${PURCHASE_ORDERS_DOMAIN}`

/** Centralized query keys of the purchase-orders feature. */
export const purchaseOrderKeys = {
  all: [PURCHASE_ORDERS_DOMAIN] as const,
  detail: (id: number) => [PURCHASE_ORDERS_DOMAIN, 'detail', id] as const,
  mergeCandidates: (id: number) => [PURCHASE_ORDERS_DOMAIN, 'merge-candidates', id] as const,
  mergeLogs: (id: number) => [PURCHASE_ORDERS_DOMAIN, 'merge-logs', id] as const,
  receipts: (id: number) => [PURCHASE_ORDERS_DOMAIN, 'receipts', id] as const,
}

export async function fetchPurchaseOrder(id: number): Promise<PurchaseOrder> {
  const { data } = await apiClient.get<ApiResponse<PurchaseOrder>>(`${ENDPOINT}/${id}`)
  return data.data
}

/** Creates the order, or N monthly copies when `payload.copies` > 1; resolves with the first one and every id. */
export async function createPurchaseOrder(payload: PurchaseOrderPayload): Promise<CreatedPurchaseOrder> {
  const { data } = await apiClient.post<ApiResponse<PurchaseOrder> & { meta?: { created_ids?: number[] } }>(
    ENDPOINT,
    payload,
  )
  return { order: data.data, createdIds: data.meta?.created_ids ?? [data.data.id] }
}

/** 409 when the order is not draft/in_validation (D-10). */
export async function updatePurchaseOrder(id: number, payload: PurchaseOrderPayload): Promise<PurchaseOrder> {
  const { data } = await apiClient.put<ApiResponse<PurchaseOrder>>(`${ENDPOINT}/${id}`, payload)
  return data.data
}

/** 409 when the order is not draft/in_validation (D-10); linked RDA lines return to approved server-side (D-18). */
export async function deletePurchaseOrder(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}`)
}

/** 403 on a missing permission, 422 `to_status` on a transition outside the matrix. */
export async function changePurchaseOrderStatus(id: number, toStatus: PurchaseOrderStatus): Promise<PurchaseOrder> {
  const { data } = await apiClient.post<ApiResponse<PurchaseOrder>>(`${ENDPOINT}/${id}/status`, {
    to_status: toStatus,
  })
  return data.data
}

/** Orders of the same supplier and company, draft/in_validation, the current one excluded (D-19). */
export async function fetchMergeCandidates(id: number): Promise<MergeCandidate[]> {
  const { data } = await apiClient.get<ApiResponse<MergeCandidate[]>>(`${ENDPOINT}/${id}/merge-candidates`)
  return data.data
}

/** Moves the lines onto the order immediately and all-or-nothing (D-19): 409 non-editable, 422 `line_ids.N`. */
export async function mergePurchaseOrders(id: number, lineIds: number[]): Promise<MergeResult> {
  const { data } = await apiClient.post<
    ApiResponse<PurchaseOrder> & { meta?: { deleted_purchase_order_ids?: number[] } }
  >(`${ENDPOINT}/${id}/merge`, { line_ids: lineIds })
  return { order: data.data, deletedIds: data.meta?.deleted_purchase_order_ids ?? [] }
}

/** Merge history of an order, most recent first. */
export async function fetchMergeLogs(id: number): Promise<MergeLog[]> {
  const { data } = await apiClient.get<ApiResponse<MergeLog[]>>(`${ENDPOINT}/${id}/merge-logs`)
  return data.data
}

/** Receipts of an order, most recent first (spec 0254). */
export async function fetchReceipts(orderId: number): Promise<PurchaseOrderReceipt[]> {
  const { data } = await apiClient.get<ApiResponse<PurchaseOrderReceipt[]>>(`${ENDPOINT}/${orderId}/receipts`)
  return data.data
}

/** 409 when the order cannot receive (D-5); 422 `lines.N.quantity` / `lines.N.purchase_order_line_id`. */
export async function createReceipt(orderId: number, payload: PurchaseOrderReceiptPayload): Promise<CreatedReceipt> {
  const { data } = await apiClient.post<ApiResponse<CreatedReceipt>>(`${ENDPOINT}/${orderId}/receipts`, payload)
  return data.data
}

/** Cancels a receipt; 409 when it would drop the received quantity below the invoiced one (D-8). */
export async function deleteReceipt(orderId: number, receiptId: number): Promise<PurchaseOrder> {
  const { data } = await apiClient.delete<ApiResponse<PurchaseOrder>>(`${ENDPOINT}/${orderId}/receipts/${receiptId}`)
  return data.data
}

/** Closes the missing quantity of a line; 409 on a complete/closed line or an over-invoiced one; 422 `reason`. */
export async function closeLineResidual(orderId: number, lineId: number, reason: string): Promise<PurchaseOrder> {
  const { data } = await apiClient.post<ApiResponse<PurchaseOrder>>(
    `${ENDPOINT}/${orderId}/lines/${lineId}/close-residual`,
    { reason },
  )
  return data.data
}

/** Reopens a closed residual; 409 when it is not closed. */
export async function reopenLineResidual(orderId: number, lineId: number): Promise<PurchaseOrder> {
  const { data } = await apiClient.delete<ApiResponse<PurchaseOrder>>(
    `${ENDPOINT}/${orderId}/lines/${lineId}/close-residual`,
  )
  return data.data
}

/**
 * Generates and downloads the order PDF (`GET /purchase-orders/{id}/pdf`): a
 * binary response, not the envelope. 422 when no layout is active for the module.
 */
export async function downloadPurchaseOrderPdf(id: number): Promise<void> {
  try {
    const response = await apiClient.get<Blob>(`${ENDPOINT}/${id}/pdf`, { responseType: 'blob' })
    saveBlob(
      response.data,
      filenameFromContentDisposition(response.headers['content-disposition']) ?? `purchase_order_${id}.pdf`,
    )
  } catch (error) {
    throw await normalizeBlobError(error)
  }
}
