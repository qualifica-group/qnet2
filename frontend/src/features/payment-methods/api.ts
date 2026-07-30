import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreatePaymentMethodPayload,
  PaymentMethodDetail,
  PaymentMethodDetailWithPermissions,
  UpdatePaymentMethodPayload,
} from '@/features/payment-methods/types'

/**
 * Fetches a single payment method detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchPaymentMethod(id: number): Promise<PaymentMethodDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<PaymentMethodDetail, ResourcePermissions>
  >(`/payment-methods/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a payment method. Returns the created resource from the envelope `data`. */
export async function createPaymentMethod(
  payload: CreatePaymentMethodPayload,
): Promise<PaymentMethodDetail> {
  const { data } = await apiClient.post<ApiResponse<PaymentMethodDetail>>(
    '/payment-methods',
    payload,
  )
  return data.data
}

/**
 * Partially updates a payment method (PATCH). `code` is never a key of
 * `UpdatePaymentMethodPayload` (D-3): the backend rejects its mere presence
 * with 422 regardless of role. Returns the updated resource.
 */
export async function updatePaymentMethod(
  id: number,
  payload: UpdatePaymentMethodPayload,
): Promise<PaymentMethodDetail> {
  const { data } = await apiClient.patch<ApiResponse<PaymentMethodDetail>>(
    `/payment-methods/${id}`,
    payload,
  )
  return data.data
}

/**
 * Deletes a payment method. Backend responds 204 with no body; no delete
 * guard exists in this iteration (D-2, no consumer yet).
 */
export async function deletePaymentMethod(id: number): Promise<void> {
  await apiClient.delete(`/payment-methods/${id}`)
}
