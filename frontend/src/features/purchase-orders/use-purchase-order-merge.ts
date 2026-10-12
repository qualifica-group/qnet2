import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { fetchMergeCandidates, mergePurchaseOrders, purchaseOrderKeys } from '@/features/purchase-orders/api'
import { toggleLine, toggleOrder } from '@/features/purchase-orders/purchase-order-merge-selection'
import type { MergeCandidate, MergeResult } from '@/features/purchase-orders/types'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

/**
 * State of the merge dialog (D-19): the candidates, the picked line ids and
 * the all-or-nothing merge. The merge is immediate on the server, so success
 * refreshes every order query and the caller closes the dialog.
 */
export function usePurchaseOrderMerge(orderId: number, onMerged: (result: MergeResult) => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [selected, setSelected] = useState<ReadonlySet<number>>(new Set())

  const candidates = useQuery<MergeCandidate[], AxiosError>({
    queryKey: purchaseOrderKeys.mergeCandidates(orderId),
    queryFn: () => fetchMergeCandidates(orderId),
    gcTime: 0,
  })

  const mutation = useMutation({
    mutationFn: () => mergePurchaseOrders(orderId, [...selected]),
    onSuccess: (result) => {
      void queryClient.invalidateQueries({ queryKey: purchaseOrderKeys.all })
      toast.success(t('purchaseOrderActions.merge.done', { count: selected.size }))
      if (result.deletedIds.length > 0) {
        toast.info(t('purchaseOrderActions.merge.deleted', { count: result.deletedIds.length }))
      }
      onMerged(result)
    },
    onError: (error) => toast.error(serverMessage(error) ?? t('purchaseOrderActions.merge.error')),
  })

  return {
    candidates,
    selected,
    toggleOrder: (candidate: MergeCandidate) => setSelected((current) => toggleOrder(candidate, current)),
    toggleLine: (lineId: number) => setSelected((current) => toggleLine(lineId, current)),
    canConfirm: selected.size > 0 && !mutation.isPending,
    isMerging: mutation.isPending,
    confirm: () => mutation.mutate(),
  }
}
