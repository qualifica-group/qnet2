import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import { fetchWorkOrderCosts, syncWorkOrderCosts } from '@/features/work-order-costs/api'
import { workOrderCostKeys } from '@/features/work-order-costs/query-keys'
import type { SyncWorkOrderCostsPayload, WorkOrderCostOverview } from '@/features/work-order-costs/types'

export function useWorkOrderCosts(workOrderId: number) {
  return useQuery<WorkOrderCostOverview, AxiosError<ApiErrorResponse>>({
    queryKey: workOrderCostKeys.overview(workOrderId),
    queryFn: () => fetchWorkOrderCosts(workOrderId),
  })
}

/** The PUT answers with the refreshed overview: it replaces the cached one, so the comparison updates with no refetch. */
export function useSyncWorkOrderCosts(workOrderId: number) {
  const queryClient = useQueryClient()
  return useMutation<WorkOrderCostOverview, AxiosError<ApiErrorResponse>, SyncWorkOrderCostsPayload>({
    mutationFn: (payload) => syncWorkOrderCosts(workOrderId, payload),
    onSuccess: (overview) => {
      queryClient.setQueryData(workOrderCostKeys.overview(workOrderId), overview)
      return queryClient.invalidateQueries({ queryKey: workOrderCostKeys.overview(workOrderId) })
    },
  })
}
