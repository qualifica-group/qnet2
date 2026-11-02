import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { purchaseInvoiceKeys } from '@/features/purchase-invoices/api'
import {
  clearPurchasePayment,
  getPurchaseInstallment,
  purchaseInstallmentKeys,
  recordPurchasePayment,
} from '@/features/purchase-invoice-installments/api'
import type {
  PurchaseInstallmentDetail,
  PurchasePaymentPayload,
} from '@/features/purchase-invoice-installments/types'

/** A payment changes the document too (paid, residual, status): every surface showing it reloads. */
function invalidatePaymentSurfaces(queryClient: ReturnType<typeof useQueryClient>): void {
  void queryClient.invalidateQueries({ queryKey: purchaseInstallmentKeys.all })
  void queryClient.invalidateQueries({ queryKey: purchaseInvoiceKeys.all })
}

/** One installment for the edit dialog; always refetched on open (a payment may have landed meanwhile). */
export function usePurchaseInstallment(id: number | null): UseQueryResult<PurchaseInstallmentDetail, AxiosError> {
  return useQuery<PurchaseInstallmentDetail, AxiosError>({
    queryKey: purchaseInstallmentKeys.detail(id ?? 0),
    queryFn: () => getPurchaseInstallment(id as number),
    enabled: id !== null,
    gcTime: 0,
  })
}

export function useRecordPurchasePayment(
  id: number,
): UseMutationResult<PurchaseInstallmentDetail, AxiosError, PurchasePaymentPayload> {
  const queryClient = useQueryClient()
  return useMutation<PurchaseInstallmentDetail, AxiosError, PurchasePaymentPayload>({
    mutationFn: (payload) => recordPurchasePayment(id, payload),
    onSuccess: () => invalidatePaymentSurfaces(queryClient),
  })
}

export function useClearPurchasePayment(): UseMutationResult<PurchaseInstallmentDetail, AxiosError, number> {
  const queryClient = useQueryClient()
  return useMutation<PurchaseInstallmentDetail, AxiosError, number>({
    mutationFn: clearPurchasePayment,
    onSuccess: () => invalidatePaymentSurfaces(queryClient),
  })
}
