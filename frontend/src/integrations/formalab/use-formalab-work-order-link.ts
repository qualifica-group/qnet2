import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { workOrderDetailQueryKey } from '@/features/work-orders/api'
import { fetchFormalabWorkOrderLink, syncFormalabWorkOrderLink } from '@/integrations/formalab/api'
import { serverMessage } from '@/integrations/formalab/error-message'
import { formalabKeys } from '@/integrations/formalab/query-keys'
import type { FormalabWorkOrderLink } from '@/integrations/formalab/types'

/** The work order's link state; `enabled` false (integration inactive) fetches nothing. */
export function useFormalabWorkOrderLink(workOrderId: number, enabled: boolean) {
  return useQuery({
    queryKey: formalabKeys.workOrderLink(workOrderId),
    queryFn: () => fetchFormalabWorkOrderLink(workOrderId),
    enabled,
    retry: false,
  })
}

/**
 * "Sincronizza": the server matches the edition by course name and rewrites
 * the course fields, so the cached link AND the work order detail are refreshed.
 */
export function useFormalabSync(workOrderId: number) {
  const { t } = useTranslation('formalab')
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => syncFormalabWorkOrderLink(workOrderId),
    onSuccess: (result: FormalabWorkOrderLink) => {
      queryClient.setQueryData(formalabKeys.workOrderLink(workOrderId), result)
      void queryClient.invalidateQueries({ queryKey: workOrderDetailQueryKey(workOrderId) })
      toast.success(t('workOrder.synced'))
    },
    onError: (error) => toast.error(serverMessage(error, t('workOrder.syncFailed'))),
  })
}
