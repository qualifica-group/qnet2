import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  BulkCollectionPayload,
  BulkCollectionResult,
  InstallmentDetail,
  InstallmentUpdatePayload,
} from '@/features/invoice-installments/types'

/** Table/module domain key (SSRM domain, permission prefix and query-key root). */
export const INVOICE_INSTALLMENTS_DOMAIN = 'invoice-installments'

/** Centralized query keys; every installment query hangs off `INVOICE_INSTALLMENTS_DOMAIN`. */
export const installmentKeys = {
  all: [INVOICE_INSTALLMENTS_DOMAIN] as const,
  detail: (id: number) => [INVOICE_INSTALLMENTS_DOMAIN, 'detail', id] as const,
}

/** Reads one installment with its invoice summary, field permissions and abilities. */
export async function getInstallment(id: number): Promise<InstallmentDetail> {
  const { data } = await apiClient.get<ApiResponse<InstallmentDetail>>(`/invoice-installments/${id}`)
  return data.data
}

/** Updates due date and/or payment method code. 409 when the installment has a collection. */
export async function updateInstallment(
  id: number,
  payload: InstallmentUpdatePayload,
): Promise<InstallmentDetail> {
  const { data } = await apiClient.patch<ApiResponse<InstallmentDetail>>(
    `/invoice-installments/${id}`,
    payload,
  )
  return data.data
}

/** Collects several installments of one customer at once (spec 0198): all or nothing. */
export async function bulkCollectInstallments(payload: BulkCollectionPayload): Promise<BulkCollectionResult> {
  const { data } = await apiClient.post<ApiResponse<BulkCollectionResult>>('/invoice-installments/collections', payload)
  return data.data
}
