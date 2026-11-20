import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { workOrderDetailQueryKey } from '@/features/work-orders/api'
import {
  fetchFormalabClasses,
  fetchFormalabWorkOrderLink,
  removeFormalabWorkOrderLink,
  setFormalabWorkOrderLink,
  syncFormalabWorkOrderLink,
} from '@/integrations/formalab/api'
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

/** Catalogue search for the edition picker; the select debounces the term. */
export function useFormalabClasses(search: string, enabled: boolean) {
  return useQuery({
    queryKey: formalabKeys.classes(search),
    queryFn: () => fetchFormalabClasses(search),
    enabled,
    staleTime: 30_000,
  })
}

/**
 * Set/change and remove the link. The server rewrites the course fields of the
 * work order, so the cached link AND the work order detail are refreshed.
 */
export function useFormalabLinkMutations(workOrderId: number) {
  const { t } = useTranslation('formalab')
  const queryClient = useQueryClient()

  const onDone = (result: FormalabWorkOrderLink, message: string) => {
    queryClient.setQueryData(formalabKeys.workOrderLink(workOrderId), result)
    void queryClient.invalidateQueries({ queryKey: workOrderDetailQueryKey(workOrderId) })
    toast.success(message)
  }

  const link = useMutation({
    mutationFn: (editionId: number) => setFormalabWorkOrderLink(workOrderId, editionId),
    onSuccess: (result) => onDone(result, t('workOrder.linked')),
    onError: (error) => toast.error(serverMessage(error, t('workOrder.linkError'))),
  })

  const sync = useMutation({
    mutationFn: () => syncFormalabWorkOrderLink(workOrderId),
    onSuccess: (result) => onDone(result, t('workOrder.synced')),
    onError: (error) => toast.error(serverMessage(error, t('workOrder.syncFailed'))),
  })

  const unlink = useMutation({
    mutationFn: () => removeFormalabWorkOrderLink(workOrderId),
    onSuccess: (result) => onDone(result, t('workOrder.unlinked')),
    onError: (error) => toast.error(serverMessage(error, t('workOrder.unlinkError'))),
  })

  return { link, sync, unlink }
}
