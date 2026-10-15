import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  BulkPaymentPayload,
  BulkPaymentResult,
  PurchaseInstallmentDetail,
  PurchasePaymentPayload,
} from '@/features/purchase-invoice-installments/types'

/** Table/module domain key (SSRM domain, permission prefix and query-key root). */
export const PURCHASE_INSTALLMENTS_DOMAIN = 'purchase-invoice-installments'

const BASE_PATH = '/purchase-invoice-installments'

/** Centralized query keys; every payable installment query hangs off `PURCHASE_INSTALLMENTS_DOMAIN`. */
export const purchaseInstallmentKeys = {
  all: [PURCHASE_INSTALLMENTS_DOMAIN] as const,
  detail: (id: number) => [PURCHASE_INSTALLMENTS_DOMAIN, 'detail', id] as const,
}

/** Reads one installment with its document summary, field permissions and abilities. */
export async function getPurchaseInstallment(id: number): Promise<PurchaseInstallmentDetail> {
  const { data } = await apiClient.get<ApiResponse<PurchaseInstallmentDetail>>(`${BASE_PATH}/${id}`)
  return data.data
}

/** Records a total or partial payment; returns the paid installment reloaded. */
export async function recordPurchasePayment(
  id: number,
  payload: PurchasePaymentPayload,
): Promise<PurchaseInstallmentDetail> {
  const { data } = await apiClient.put<ApiResponse<PurchaseInstallmentDetail>>(`${BASE_PATH}/${id}/payment`, payload)
  return data.data
}

/** Clears a payment, restoring the plan from its snapshot. 409 when a later touched installment was paid. */
export async function clearPurchasePayment(id: number): Promise<PurchaseInstallmentDetail> {
  const { data } = await apiClient.delete<ApiResponse<PurchaseInstallmentDetail>>(`${BASE_PATH}/${id}/payment`)
  return data.data
}

/** Pays several installments of one supplier at once (spec 0211 D-8): all or nothing. */
export async function bulkPayInstallments(payload: BulkPaymentPayload): Promise<BulkPaymentResult> {
  const { data } = await apiClient.post<ApiResponse<BulkPaymentResult>>(`${BASE_PATH}/payments`, payload)
  return data.data
}
