import { useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import { fetchWorkOrderInvoices } from '@/features/work-order-invoices/api'
import { workOrderInvoiceKeys } from '@/features/work-order-invoices/query-keys'
import type { WorkOrderInvoices } from '@/features/work-order-invoices/types'

/** One query shared by the active and the passive tab: same key, a single request. */
export function useWorkOrderInvoices(workOrderId: number) {
  return useQuery<WorkOrderInvoices, AxiosError<ApiErrorResponse>>({
    queryKey: workOrderInvoiceKeys.list(workOrderId),
    queryFn: () => fetchWorkOrderInvoices(workOrderId),
  })
}

/** Refetches the tabs after a document was changed from its detail sheet. */
export function useRefreshWorkOrderInvoices(workOrderId: number): () => void {
  const queryClient = useQueryClient()
  return () => {
    void queryClient.invalidateQueries({ queryKey: workOrderInvoiceKeys.list(workOrderId) })
  }
}
