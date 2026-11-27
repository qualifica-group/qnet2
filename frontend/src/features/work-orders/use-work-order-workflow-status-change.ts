import axios from 'axios'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { notifyFormErrors } from '@/features/auth/form-errors'
import { updateWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'
import { apiErrorMessage } from '@/lib/forbidden-message'

const HTTP_UNPROCESSABLE = 422

/** The reasons of a 422 (e.g. spec 0323 D-2, a status that would not raise the progress), empty otherwise. */
function validationMessages(error: unknown): string[] {
  if (!axios.isAxiosError(error) || error.response?.status !== HTTP_UNPROCESSABLE) {
    return []
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined

  return Object.values(errors ?? {}).flatMap((messages) => messages?.[0] ?? [])
}

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
    onError: (error) => {
      const messages = validationMessages(error)
      if (messages.length > 0) {
        notifyFormErrors(messages)
        return
      }
      toast.error(apiErrorMessage(error, t('workOrders.form.genericError'), i18n))
    },
  })
}
