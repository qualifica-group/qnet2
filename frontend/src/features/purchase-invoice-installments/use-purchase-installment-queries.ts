import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query'
import type { AxiosError } from 'axios'
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
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: purchaseInstallmentKeys.all }),
  })
}

export function useClearPurchasePayment(): UseMutationResult<PurchaseInstallmentDetail, AxiosError, number> {
  const queryClient = useQueryClient()
  return useMutation<PurchaseInstallmentDetail, AxiosError, number>({
    mutationFn: clearPurchasePayment,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: purchaseInstallmentKeys.all }),
  })
}
