import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { updateWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import { notifyWorkOrderSaveError } from '@/features/work-orders/work-order-save-errors'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/**
 * Changes the "Stato lavorazione" (spec 0322) from the record header (user
 * directive 2026-11-27): a PATCH of that field alone. The saved record goes
 * into the detail cache, so the header and the edit form re-sync on it; a
 * refusal is a foreground toast with the server's reasons.
 */
export function useWorkOrderWorkflowStatusChange(workOrderId: number, onChanged?: () => void) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()

  return useMutation<WorkOrderDetailWithPermissions, unknown, number>({
    mutationFn: (statusId) => updateWorkOrder(workOrderId, { work_order_workflow_status_id: statusId }),
    onSuccess: (saved) => {
      queryClient.setQueryData(workOrderDetailQueryKey(workOrderId), saved)
      toast.success(t('workOrders.form.updated'))
      onChanged?.()
    },
    onError: (error) => notifyWorkOrderSaveError(error, t, i18n),
  })
}
