import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { invoiceKeys, setInvoiceLineRevenueType } from '@/features/invoices/api'
import type { InvoiceLineRevenueTypeResult } from '@/features/invoices/types'

interface SetLineRevenueTypeVariables {
  lineId: number
  revenueTypeId: number | null
}

/** Reclassifies one line and refreshes the document detail; a 403/422 reaches the caller's `onError`. */
export function useSetInvoiceLineRevenueType(
  invoiceId: number,
): UseMutationResult<InvoiceLineRevenueTypeResult, AxiosError, SetLineRevenueTypeVariables> {
  const queryClient = useQueryClient()
  return useMutation<InvoiceLineRevenueTypeResult, AxiosError, SetLineRevenueTypeVariables>({
    mutationFn: ({ lineId, revenueTypeId }) => setInvoiceLineRevenueType(invoiceId, lineId, revenueTypeId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: invoiceKeys.detail(invoiceId) }),
  })
}
