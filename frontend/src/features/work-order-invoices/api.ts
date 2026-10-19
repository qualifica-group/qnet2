import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { WorkOrderInvoices } from '@/features/work-order-invoices/types'

/** `GET /work-orders/{id}/invoices`: active and passive documents plus totals, read-only. */
export async function fetchWorkOrderInvoices(workOrderId: number): Promise<WorkOrderInvoices> {
  const { data } = await apiClient.get<ApiResponse<WorkOrderInvoices>>(`/work-orders/${workOrderId}/invoices`)
  return data.data
}
