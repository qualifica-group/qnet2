import { useCallback } from 'react'
import axios from 'axios'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  deletePurchaseRequest,
  notifyPurchaseRequestManager,
  purchaseRequestKeys,
} from '@/features/purchase-requests/api'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

const FORBIDDEN_STATUS = 403
const CONFLICT_STATUS = 409

/**
 * Deletes an RDA and reports the outcome: 403 (no permission) and 409 (a line
 * is ordered or received, with the backend's own message) get their text.
 * Resolves true when the RDA is gone. The caller asks for confirmation.
 */
export function usePurchaseRequestDelete() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  return useCallback(
    async (id: number): Promise<boolean> => {
      try {
        await deletePurchaseRequest(id)
        void queryClient.invalidateQueries({ queryKey: purchaseRequestKeys.all })
        toast.success(t('purchaseRequests.messages.deleted'))
        return true
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === FORBIDDEN_STATUS) {
          toast.error(t('purchaseRequests.messages.deleteForbidden'))
        } else if (status === CONFLICT_STATUS) {
          toast.error(serverMessage(error) ?? t('purchaseRequests.messages.deleteBlocked'))
        } else {
          toast.error(t('purchaseRequests.messages.deleteError'))
        }
        return false
      }
    },
    [queryClient, t],
  )
}

/** "Send to the manager": re-sends the creation notification on request. */
export function useNotifyManager(requestId: number) {
  const { t } = useTranslation()
  return useMutation({
    mutationFn: () => notifyPurchaseRequestManager(requestId),
    onSuccess: (message) => toast.success(message),
    onError: (error) => toast.error(serverMessage(error) ?? t('purchaseRequests.messages.notifyError')),
  })
}
