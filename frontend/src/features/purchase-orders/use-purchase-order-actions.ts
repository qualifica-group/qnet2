import { useCallback } from 'react'
import axios from 'axios'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { deletePurchaseOrder, purchaseOrderKeys } from '@/features/purchase-orders/api'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

const FORBIDDEN_STATUS = 403
const CONFLICT_STATUS = 409

/**
 * Deletes an ODA and reports the outcome: 403 (no permission) and 409 (not
 * draft/in_validation, with the backend's own message) get their text.
 * Resolves true when the order is gone. The caller asks for confirmation.
 */
export function usePurchaseOrderDelete() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  return useCallback(
    async (id: number): Promise<boolean> => {
      try {
        await deletePurchaseOrder(id)
        void queryClient.invalidateQueries({ queryKey: purchaseOrderKeys.all })
        toast.success(t('purchaseOrders.messages.deleted'))
        return true
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === FORBIDDEN_STATUS) {
          toast.error(t('purchaseOrders.messages.deleteForbidden'))
        } else if (status === CONFLICT_STATUS) {
          toast.error(serverMessage(error) ?? t('purchaseOrders.messages.deleteBlocked'))
        } else {
          toast.error(t('purchaseOrders.messages.deleteError'))
        }
        return false
      }
    },
    [queryClient, t],
  )
}
