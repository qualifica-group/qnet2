import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import {
  createPurchaseInvoice,
  fetchInvoiceableOrders,
  fetchPurchaseInvoice,
  fetchPurchaseInvoiceDraft,
  previewPurchaseInvoiceInstallments,
  purchaseInvoiceKeys,
} from '@/features/purchase-invoices/api'
import { PURCHASE_INSTALLMENTS_DOMAIN } from '@/features/purchase-invoice-installments/api'
import type {
  InvoiceableOrder,
  PurchaseInstallmentPreviewPayload,
  PurchaseInstallmentPreviewRow,
  PurchaseInvoice,
  PurchaseInvoiceDraft,
  PurchaseInvoiceWritePayload,
} from '@/features/purchase-invoices/types'

const EMPTY_PREVIEW: PurchaseInstallmentPreviewPayload = {
  document_date: '',
  payment_method_id: 0,
  net_amount: 0,
  vat_amount: 0,
  total_amount: 0,
}

/** One document with rows, installments and permissions; idle while `id` is null. */
export function usePurchaseInvoice(id: number | null): UseQueryResult<PurchaseInvoice, AxiosError> {
  return useQuery<PurchaseInvoice, AxiosError>({
    queryKey: purchaseInvoiceKeys.detail(id ?? 0),
    queryFn: () => fetchPurchaseInvoice(id as number),
    enabled: id !== null,
  })
}

/** Draft from ODAs; always refetched on open (an order may have been invoiced meanwhile). */
export function usePurchaseInvoiceDraft(
  orderIds: readonly number[] | null,
): UseQueryResult<PurchaseInvoiceDraft, AxiosError> {
  return useQuery<PurchaseInvoiceDraft, AxiosError>({
    queryKey: purchaseInvoiceKeys.draft(orderIds ?? []),
    queryFn: () => fetchPurchaseInvoiceDraft(orderIds as readonly number[]),
    enabled: orderIds !== null && orderIds.length > 0,
    gcTime: 0,
  })
}

/** Invoiceable ODAs of a supplier and company; idle until both are chosen. */
export function useInvoiceableOrders(
  supplierId: number | null,
  companyId: number | null,
): UseQueryResult<InvoiceableOrder[], AxiosError> {
  return useQuery<InvoiceableOrder[], AxiosError>({
    queryKey: purchaseInvoiceKeys.invoiceableOrders(supplierId ?? 0, companyId ?? 0),
    queryFn: () => fetchInvoiceableOrders(supplierId as number, companyId as number),
    enabled: supplierId !== null && companyId !== null,
  })
}

/** Installment plan preview; pass `null` to keep it idle until the inputs are valid. */
export function usePurchaseInstallmentPreview(
  payload: PurchaseInstallmentPreviewPayload | null,
): UseQueryResult<PurchaseInstallmentPreviewRow[], AxiosError> {
  return useQuery<PurchaseInstallmentPreviewRow[], AxiosError>({
    queryKey: purchaseInvoiceKeys.preview(payload ?? EMPTY_PREVIEW),
    queryFn: () => previewPurchaseInvoiceInstallments(payload as PurchaseInstallmentPreviewPayload),
    enabled: payload !== null,
  })
}

/** Refreshes documents and the payable installments grid (its rows mirror the document). */
function useInvalidateDocuments() {
  const queryClient = useQueryClient()
  return () => {
    void queryClient.invalidateQueries({ queryKey: purchaseInvoiceKeys.all })
    void queryClient.invalidateQueries({ queryKey: [PURCHASE_INSTALLMENTS_DOMAIN] })
  }
}

export function useCreatePurchaseInvoice(): UseMutationResult<PurchaseInvoice, AxiosError, PurchaseInvoiceWritePayload> {
  const invalidate = useInvalidateDocuments()
  return useMutation<PurchaseInvoice, AxiosError, PurchaseInvoiceWritePayload>({
    mutationFn: createPurchaseInvoice,
    onSuccess: invalidate,
  })
}
