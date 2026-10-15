import { useCallback } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import axios, { type AxiosError } from 'axios'
import { toast } from 'sonner'
import { PURCHASE_INSTALLMENTS_DOMAIN } from '@/features/purchase-invoice-installments/api'
import { deletePurchaseInvoice, purchaseInvoiceKeys } from '@/features/purchase-invoices/api'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

const HTTP_CONFLICT = 409

/** Delete mutation with the user-facing outcomes: success, 409 (registered invoice or payments), generic error. */
export function useDeletePurchaseInvoiceFlow(onDeleted: () => void, onSettled: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { mutate, isPending } = useMutation<void, AxiosError, number>({
    mutationFn: deletePurchaseInvoice,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: purchaseInvoiceKeys.all })
      void queryClient.invalidateQueries({ queryKey: [PURCHASE_INSTALLMENTS_DOMAIN] })
    },
  })

  const confirm = useCallback(
    (id: number) =>
      mutate(id, {
        onSuccess: () => {
          toast.success(t('purchaseInvoiceViews.delete.deleted'))
          onDeleted()
        },
        onError: (error) => {
          const conflict = axios.isAxiosError(error) && error.response?.status === HTTP_CONFLICT
          // The 409 message names the cause (payments, registered invoice, settlement_locked); the generic text is the fallback.
          const message = conflict ? serverMessage(error) : null
          toast.error(message ?? t(conflict ? 'purchaseInvoiceViews.delete.locked' : 'purchaseInvoiceViews.delete.genericError'))
        },
        onSettled,
      }),
    [mutate, onDeleted, onSettled, t],
  )

  return { confirm, isPending }
}
