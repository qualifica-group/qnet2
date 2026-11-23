import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useTranslation } from 'react-i18next'
import { copyWorkOrderQuoteAttributes, workOrderDetailQueryKey } from '@/features/work-orders/api'

/**
 * "Copia dati dall'offerta" (spec 0404 D-D2): the server fills only the empty
 * flexible fields; the success toast is its own message (it carries the count),
 * and the detail cache takes the saved record.
 */
export function useWorkOrderCopyQuoteAttributes(workOrderId: number, onDone?: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => copyWorkOrderQuoteAttributes(workOrderId),
    onSuccess: ({ message, workOrder }) => {
      queryClient.setQueryData(workOrderDetailQueryKey(workOrderId), workOrder)
      toast.success(message)
      onDone?.()
    },
    onError: () => {
      toast.error(t('workOrders.actions.copyQuoteAttributes.error'))
    },
  })
}
