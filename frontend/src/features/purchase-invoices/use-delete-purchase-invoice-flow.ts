import { useCallback } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import axios, { type AxiosError } from 'axios'
import { toast } from 'sonner'
import { PURCHASE_INSTALLMENTS_DOMAIN } from '@/features/purchase-invoice-installments/api'
import { deletePurchaseInvoice, purchaseInvoiceKeys } from '@/features/purchase-invoices/api'

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
          toast.error(t(conflict ? 'purchaseInvoiceViews.delete.locked' : 'purchaseInvoiceViews.delete.genericError'))
        },
        onSettled,
      }),
    [mutate, onDeleted, onSettled, t],
  )

  return { confirm, isPending }
}
