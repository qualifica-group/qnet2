import { useCallback } from 'react'
import axios from 'axios'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { forbiddenMessage } from '@/lib/forbidden-message'
import { deletePurchaseOrder, purchaseOrderKeys } from '@/features/purchase-orders/api'
import { purchaseRequestKeys } from '@/features/purchase-requests/api'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

const CONFLICT_STATUS = 409

/**
 * Deletes an ODA and reports the outcome: 403 (no permission) and 409 (not
 * draft/in_validation, with the backend's own message) get their text.
 * Resolves true when the order is gone. The caller asks for confirmation.
 */
export function usePurchaseOrderDelete() {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()

  return useCallback(
    async (id: number): Promise<boolean> => {
      try {
        await deletePurchaseOrder(id)
        void queryClient.invalidateQueries({ queryKey: purchaseOrderKeys.all })
        // The order status and deletion move the linked RDA lines (spec 0244 D-2/D-3)
        void queryClient.invalidateQueries({ queryKey: purchaseRequestKeys.all })
        toast.success(t('purchaseOrders.messages.deleted'))
        return true
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        const forbidden = forbiddenMessage(error, i18n)
        if (forbidden) {
          toast.error(forbidden)
        } else if (status === CONFLICT_STATUS) {
          toast.error(serverMessage(error) ?? t('purchaseOrders.messages.deleteBlocked'))
        } else {
          toast.error(t('purchaseOrders.messages.deleteError'))
        }
        return false
      }
    },
    [queryClient, t, i18n],
  )
}
