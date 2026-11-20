import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import { fetchWorkOrderPage } from '@/features/work-order-page/api'
import { workOrderPageKeys } from '@/features/work-order-page/query-keys'
import type { WorkOrderPagePayload } from '@/features/work-order-page/types'

/**
 * The page payload. Its `content` only seeds the editor: later refetches
 * refresh `tasks`/`polls`, never the text being edited.
 */
export function useWorkOrderPage(workOrderId: number) {
  return useQuery<WorkOrderPagePayload, AxiosError<ApiErrorResponse>>({
    queryKey: workOrderPageKeys.page(workOrderId),
    queryFn: () => fetchWorkOrderPage(workOrderId),
    refetchOnWindowFocus: false,
  })
}
