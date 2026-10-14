import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  closeLineResidual,
  createReceipt,
  deleteReceipt,
  fetchReceipts,
  purchaseOrderKeys,
  reopenLineResidual,
} from '@/features/purchase-orders/api'
import type { PurchaseOrder, PurchaseOrderReceipt, PurchaseOrderReceiptPayload } from '@/features/purchase-orders/types'
import { purchaseInvoiceKeys } from '@/features/purchase-invoices/api'
import { purchaseRequestKeys } from '@/features/purchase-requests/api'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

/**
 * Publishes the refreshed order and invalidates what a receipt or a residual
 * change moves: the order queries (detail, list, receipts), the linked RDA
 * lines (spec 0254 D-7) and the invoice drafts built on the receivable quantity.
 */
function useApplyFulfillmentChange() {
  const queryClient = useQueryClient()
  return (order: PurchaseOrder) => {
    queryClient.setQueryData(purchaseOrderKeys.detail(order.id), order)
    void queryClient.invalidateQueries({ queryKey: purchaseOrderKeys.all })
    void queryClient.invalidateQueries({ queryKey: purchaseRequestKeys.all })
    void queryClient.invalidateQueries({ queryKey: purchaseInvoiceKeys.all })
  }
}

/** Receipts of an order, most recent first. */
export function usePurchaseOrderReceipts(orderId: number) {
  return useQuery<PurchaseOrderReceipt[], AxiosError>({
    queryKey: purchaseOrderKeys.receipts(orderId),
    queryFn: () => fetchReceipts(orderId),
  })
}

/** Registers a receipt; the caller maps the server errors (422/409) in its dialog. */
export function useCreateReceipt(orderId: number) {
  const { t } = useTranslation()
  const apply = useApplyFulfillmentChange()
  return useMutation({
    mutationFn: (payload: PurchaseOrderReceiptPayload) => createReceipt(orderId, payload),
    onSuccess: ({ order }) => {
      apply(order)
      toast.success(t('purchaseOrderActions.receipts.created'))
    },
  })
}

export function useDeleteReceipt(orderId: number) {
  const { t } = useTranslation()
  const apply = useApplyFulfillmentChange()
  return useMutation({
    mutationFn: (receiptId: number) => deleteReceipt(orderId, receiptId),
    onSuccess: (order) => {
      apply(order)
      toast.success(t('purchaseOrderActions.receipts.deleted'))
    },
    onError: (error) => toast.error(serverMessage(error) ?? t('purchaseOrderActions.receipts.deleteError')),
  })
}

/** Closes the residual of a line; the caller shows the 409/422 inside its dialog. */
export function useCloseResidual(orderId: number) {
  const { t } = useTranslation()
  const apply = useApplyFulfillmentChange()
  return useMutation({
    mutationFn: ({ lineId, reason }: { lineId: number; reason: string }) => closeLineResidual(orderId, lineId, reason),
    onSuccess: (order) => {
      apply(order)
      toast.success(t('purchaseOrderActions.residual.closed'))
    },
  })
}

export function useReopenResidual(orderId: number) {
  const { t } = useTranslation()
  const apply = useApplyFulfillmentChange()
  return useMutation({
    mutationFn: (lineId: number) => reopenLineResidual(orderId, lineId),
    onSuccess: (order) => {
      apply(order)
      toast.success(t('purchaseOrderActions.residual.reopened'))
    },
    onError: (error) => toast.error(serverMessage(error) ?? t('purchaseOrderActions.residual.reopenError')),
  })
}
