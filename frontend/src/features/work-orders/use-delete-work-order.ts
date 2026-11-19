import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { toast } from 'sonner'
import { refusedActionMessage } from '@/lib/forbidden-message'
import { deleteWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'

export interface UseDeleteWorkOrderResult {
  /** Deletes the commessa: the caller has already asked for confirmation. */
  remove: (id: number) => Promise<void>
  isPending: boolean
}

/**
 * The commessa delete shared by the grid row and the detail header (user
 * request 2026-11-19). A 409 (accounting documents, spec 0243) shows the
 * server's reason; `onDeleted` runs only after a successful delete.
 */
export function useDeleteWorkOrder(onDeleted: () => void): UseDeleteWorkOrderResult {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const { mutateAsync, isPending } = useMutation<void, AxiosError, number>({ mutationFn: (id) => deleteWorkOrder(id) })

  const remove = useCallback(
    async (id: number) => {
      try {
        await mutateAsync(id)
      } catch (error) {
        toast.error(refusedActionMessage(error, t('workOrders.actions.delete.error'), i18n))
        return
      }
      // Removed, not invalidated: a refetch of a deleted record would only answer 404.
      queryClient.removeQueries({ queryKey: workOrderDetailQueryKey(id) })
      toast.success(t('workOrders.actions.delete.success'))
      onDeleted()
    },
    [mutateAsync, queryClient, onDeleted, t, i18n],
  )

  return { remove, isPending }
}
